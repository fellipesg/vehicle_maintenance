<?php

namespace App\Http\Controllers\Web;

use App\Enums\Portal;
use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantService;
use App\Support\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

/**
 * Entrada web: hub (/login), as 4 telas de login (/login/usuario, /login/lojista, /login/oficina e
 * /login/admin, todas na view auth.login), cadastro público de proprietário e saída.
 *
 * O hub mostra só os 3 perfis públicos; o Painel Administrador abre só pela URL direta.
 */
class AuthController extends Controller
{
    /**
     * Slug da URL de login => área que ele atende.
     *
     * @var array<string, Portal>
     */
    private const LOGIN_PORTALS = [
        'usuario' => Portal::Owner,
        'lojista' => Portal::Dealer,
        'oficina' => Portal::Workshop,
        'admin' => Portal::Admin,
    ];

    /**
     * Perfis que aparecem no hub, na ordem da tela.
     *
     * @var list<string>
     */
    private const HUB_PORTALS = ['usuario', 'lojista', 'oficina'];

    public const REGISTERED_MESSAGE = 'Conta criada. Enviamos as boas-vindas para :email. Próximo passo: adicione seu veículo.';

    public const LOGGED_OUT_MESSAGE = 'Você saiu da sua conta.';

    public function showLoginHub(Request $request): View
    {
        return view('auth.login-hub', [
            'portalOptions' => array_map(fn (string $slug): array => $this->hubOption($slug), self::HUB_PORTALS),
            'intendedNotice' => $this->intendedNotice($request),
        ]);
    }

    public function showLogin(string $portal): View
    {
        abort_unless($this->portalIsValid($portal), 404);

        return view('auth.login', [
            'portal' => $portal,
            'portalConfig' => $this->portalConfig($portal),
        ]);
    }

    public function login(Request $request, string $portal): RedirectResponse
    {
        abort_unless($this->portalIsValid($portal), 404);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'credentials' => 'E-mail ou senha incorretos.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $this->userMatchesPortal($user, self::LOGIN_PORTALS[$portal])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Credenciais certas, porta errada: a mensagem nomeia a área da conta e o link leva
            // direto para ela, com o e-mail preenchido.
            $suggestedPortal = $user->portal();

            return back()
                ->withErrors(['email' => $this->wrongPortalMessage($suggestedPortal)])
                ->with('suggested_portal', [
                    'url' => route($suggestedPortal->loginRoute()),
                    'label' => 'Ir para a '.$this->portalConfig($this->loginSlug($suggestedPortal))['title'],
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->to($this->redirectAfterLogin($request, $user, self::LOGIN_PORTALS[$portal]));
    }

    public function showRegister(Request $request): View
    {
        $errors = $request->session()->get('errors');

        return view('auth.register', [
            // "Este e-mail já está cadastrado." (lang/pt_BR/validation.php, custom.email.unique):
            // a tela oferece entrar ou redefinir a senha em vez de outro cadastro.
            'emailTaken' => $errors instanceof ViewErrorBag
                && $errors->getBag('default')->first('email') === trans('validation.custom.email.unique'),
            'intendedNotice' => $this->intendedNotice($request),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        if (in_array($request->input('user_type'), ['garage', 'workshop'], true)) {
            return back()->withErrors([
                'user_type' => 'Cadastro público disponível apenas para proprietários de veículos.',
            ])->onlyInput('name', 'email', 'phone', 'document');
        }

        $request->merge([
            'document' => $request->filled('document')
                ? preg_replace('/\D/', '', (string) $request->input('document'))
                : null,
            'phone' => $request->filled('phone')
                ? preg_replace('/\D/', '', (string) $request->input('phone'))
                : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'min:10', 'max:11'],
            'document' => ['nullable', 'string', 'regex:/^(\d{11}|\d{14})$/'],
        ], [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'document.regex' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'user',
                'phone' => $data['phone'] ?? null,
                'document' => $data['document'] ?? null,
                'country' => 'Brasil',
            ]);

            (new TenantService)->createForUser($user);

            return $user;
        });

        UserRegistered::dispatch($user, RegistrationSource::Web);

        Auth::login($user);
        $request->session()->regenerate();

        // Quem chegou por um link da área (a busca de veículo, por exemplo) volta para ele. O e-mail
        // na mensagem deixa visível um erro de digitação logo depois do cadastro.
        return redirect()
            ->to($this->redirectAfterLogin($request, $user, Portal::Owner))
            ->with('success', str_replace(':email', $user->email, self::REGISTERED_MESSAGE))
            ->with('analytics_event', 'sign_up');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', self::LOGGED_OUT_MESSAGE);
    }

    private function portalIsValid(string $portal): bool
    {
        return array_key_exists($portal, self::LOGIN_PORTALS);
    }

    private function loginSlug(Portal $portal): string
    {
        return (string) array_search($portal, self::LOGIN_PORTALS, true);
    }

    /**
     * Cabeçalho (título, descrição e ícone) e link de rodapé da tela de login de cada portal.
     *
     * @return array{title: string, subtitle: string, icon: string, footer: array{prompt: string, label: string, url: string}|null}
     */
    private function portalConfig(string $portal): array
    {
        $partnershipUrl = route('contact.show', ['assunto' => 'partnership']);

        return match (self::LOGIN_PORTALS[$portal]) {
            Portal::Admin => [
                'title' => 'Painel Administrador',
                'subtitle' => 'Acesso exclusivo para a gestão da plataforma.',
                'icon' => 'cog-6-tooth',
                'footer' => null,
            ],
            Portal::Dealer => [
                'title' => 'Área do Lojista',
                'subtitle' => 'Estoque, consignações e manutenções da sua loja.',
                'icon' => 'building-storefront',
                'footer' => ['prompt' => 'Quer trazer sua loja para a RevisaLog?', 'label' => 'Fale com a equipe', 'url' => $partnershipUrl],
            ],
            Portal::Workshop => [
                'title' => 'Área da Oficina',
                'subtitle' => 'Ordens de serviço com o Selo da oficina e o perfil da sua oficina.',
                'icon' => 'wrench-screwdriver',
                'footer' => ['prompt' => 'Quer trazer sua oficina para a RevisaLog?', 'label' => 'Fale com a equipe', 'url' => $partnershipUrl],
            ],
            Portal::Owner => [
                'title' => 'Área do Proprietário',
                'subtitle' => 'Histórico dos seus veículos e manutenções.',
                'icon' => 'user',
                'footer' => ['prompt' => 'Não tem conta?', 'label' => 'Criar conta grátis', 'url' => route('register')],
            ],
        };
    }

    /**
     * Cartão de um perfil no hub.
     *
     * @return array{slug: string, label: string, description: string, icon: string, url: string}
     */
    private function hubOption(string $slug): array
    {
        $portalConfig = $this->portalConfig($slug);

        return [
            'slug' => $slug,
            'label' => match (self::LOGIN_PORTALS[$slug]) {
                Portal::Dealer => 'Lojista',
                Portal::Workshop => 'Oficina',
                default => 'Proprietário de veículo',
            },
            'description' => match (self::LOGIN_PORTALS[$slug]) {
                Portal::Dealer => 'Loja, garagem ou revenda: estoque e manutenções.',
                Portal::Workshop => 'Ordens de serviço com o Selo da oficina.',
                default => 'Histórico dos seus veículos e manutenções.',
            },
            'icon' => $portalConfig['icon'],
            'url' => route(self::LOGIN_PORTALS[$slug]->loginRoute()),
        ];
    }

    /**
     * Por que o visitante está no hub ou no cadastro: 'search' quando o link protegido era a busca
     * de veículo, 'protected' para qualquer outra página que exige conta e null quando ele veio
     * direto.
     */
    private function intendedNotice(Request $request): ?string
    {
        $intendedUrl = $request->session()->get('url.intended');

        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return null;
        }

        $path = '/'.ltrim((string) parse_url($intendedUrl, PHP_URL_PATH), '/');

        return str_starts_with($path, '/buscar-veiculo') ? 'search' : 'protected';
    }

    /**
     * O Admin é uma área extra de quem tem is_admin; os demais portais seguem o user_type da conta
     * (os valores de Portal são os de users.user_type).
     */
    private function userMatchesPortal(User $user, Portal $portal): bool
    {
        return $portal === Portal::Admin
            ? $user->isAdmin()
            : $user->user_type === $portal->value;
    }

    private function wrongPortalMessage(Portal $suggestedPortal): string
    {
        return match ($suggestedPortal) {
            Portal::Owner => 'Esta conta é de proprietário de veículo. Entre pela Área do Proprietário.',
            Portal::Dealer => 'Esta conta é de lojista. Entre pela Área do Lojista.',
            Portal::Workshop => 'Esta conta é de oficina. Entre pela Área da Oficina.',
            Portal::Admin => 'Esta conta é de administrador. Entre pelo Painel Administrador.',
        };
    }

    /**
     * Volta à página protegida que levou ao login só quando ela é de uma área desta conta. Um
     * visitante que abriu /admin/dashboard e entrou como oficina vai ao Início da oficina, e não
     * a um 403.
     */
    private function redirectAfterLogin(Request $request, User $user, Portal $portal): string
    {
        $intendedUrl = $request->session()->pull('url.intended');

        if (is_string($intendedUrl) && PortalAccess::allowsIntendedUrl($user, $intendedUrl, $request->getHost())) {
            return $intendedUrl;
        }

        return route($portal->dashboardRoute());
    }
}
