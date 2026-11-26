<?php

namespace App\Http\Controllers\Web;

use App\Enums\Portal;
use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Signup\StoreSignupDetailsRequest;
use App\Http\Requests\Web\Signup\StoreSignupPasswordRequest;
use App\Http\Requests\Web\Signup\StoreSignupProfileRequest;
use App\Models\User;
use App\Services\User\OwnerSignupService;
use App\Services\Workshop\WorkshopSignupService;
use App\Support\Account\SignupFlow;
use App\Support\BrazilianStates;
use App\Support\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Cadastro em etapas (/cadastro): escolher o perfil e o e-mail, resolver a senha, preencher os dados
 * do perfil.
 *
 * Existe porque um e-mail pode ter uma conta por papel (App\Models\Identity) e o cadastro de uma
 * página só descobria isso tarde: a pessoa preenchia CNPJ, CEP e endereço inteiros para então ler
 * "informe a senha da sua conta". Aqui a busca do e-mail é a primeira coisa que acontece, e a senha
 * é resolvida antes de qualquer campo do perfil.
 *
 * As três etapas são rotas do grupo `guest`: **quem está logado não passa por aqui**. É o que
 * garante que ninguém troca de perfil sem sair e entrar de novo — não existe caminho de conta logada
 * para outro papel, e o menu de conta não oferece um (App\Enums\Portal::accessibleBy devolve só a
 * área da própria conta).
 *
 * O cadastro da landing (/para-oficinas/cadastro) continua existindo: é ele que carrega o `ref` da
 * prospecção e converte a prospect (.ai/rules/outreach.md).
 */
class SignupController extends Controller
{
    public const REGISTERED_OWNER_MESSAGE = 'Conta criada. Enviamos as boas-vindas para :email. Próximo passo: adicione seu veículo.';

    public function __construct(
        private OwnerSignupService $ownerSignup,
        private WorkshopSignupService $workshopSignup,
    ) {}

    /**
     * Etapa 1: o perfil e o e-mail.
     */
    public function start(Request $request): View
    {
        return view('auth.signup.start', [
            'profiles' => $this->profileOptions(),
            'flow' => SignupFlow::fromRequest($request),
        ]);
    }

    /**
     * A busca do e-mail. Três saídas: o perfil já existe nesse e-mail (recusa, com a mesma mensagem
     * de sempre), o e-mail tem conta de outro perfil (a etapa da senha pede a da identidade) ou o
     * e-mail é novo (a etapa da senha pede uma nova).
     */
    public function storeProfile(StoreSignupProfileRequest $request): RedirectResponse
    {
        SignupFlow::fromRequest($request)->start(
            $request->profile(),
            (string) $request->validated('email'),
            $request->existingIdentity(),
        );

        return redirect()->route('signup.password');
    }

    /**
     * Etapa 2: a senha. Quem não sabe a da conta que já existe sai daqui para a recuperação e volta
     * depois — não há como criar o segundo perfil sem ela.
     */
    public function password(Request $request): View
    {
        $flow = SignupFlow::fromRequest($request);

        return view('auth.signup.password', [
            'flow' => $flow,
            'email' => (string) $flow->email(),
            'joinsExistingIdentity' => $flow->joinsExistingIdentity(),
            'profileLabel' => $flow->profile()?->label(),
        ]);
    }

    public function storePassword(StoreSignupPasswordRequest $request): RedirectResponse
    {
        $flow = SignupFlow::fromRequest($request);

        if ($flow->joinsExistingIdentity()) {
            $flow->markIdentityVerified();
        } else {
            $flow->rememberNewPassword((string) $request->validated('password'));
        }

        return redirect()->route('signup.details');
    }

    /**
     * Etapa 3: os dados do perfil escolhido.
     */
    public function details(Request $request): View
    {
        $flow = SignupFlow::fromRequest($request);

        return view('auth.signup.details', [
            'flow' => $flow,
            'email' => (string) $flow->email(),
            'profile' => $flow->profile() ?? Portal::Owner,
            'stateOptions' => BrazilianStates::options(),
        ]);
    }

    public function storeDetails(StoreSignupDetailsRequest $request): RedirectResponse
    {
        $flow = SignupFlow::fromRequest($request);
        $profile = $flow->profile() ?? Portal::Owner;

        $data = [
            ...$request->validated(),
            'email' => (string) $flow->email(),
            // No caminho da conta irmã não há senha a gravar: ela é da identidade, que já existe.
            'password' => $flow->newPasswordHash(),
        ];

        $user = $profile === Portal::Workshop
            ? $this->workshopSignup->register($data, $flow->identity())
            : $this->ownerSignup->register($data, $flow->identity());

        $flow->clear();

        UserRegistered::dispatch($user, RegistrationSource::Web);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->to(PortalAccess::homeUrl($user))
            ->with('success', $this->registeredMessage($profile, $user))
            ->with('analytics_event', 'sign_up')
            ->with('analytics_method', $profile === Portal::Workshop ? 'workshop' : 'owner');
    }

    private function registeredMessage(Portal $profile, User $user): string
    {
        $template = $profile === Portal::Workshop
            ? WorkshopSignupController::REGISTERED_MESSAGE
            : self::REGISTERED_OWNER_MESSAGE;

        return str_replace(':email', (string) $user->email, $template);
    }

    /**
     * Cartões da etapa 1. O lojista aparece, mas levando ao login: a loja entra por Google ou Apple,
     * não por e-mail e senha, e isso é decisão de produto registrada em .ai/rules/web-api.md —
     * reabri-la pede verificação de loja, não um cartão novo aqui.
     *
     * @return list<array{slug: ?string, label: string, description: string, icon: string, url: ?string}>
     */
    private function profileOptions(): array
    {
        return [
            [
                'slug' => 'usuario',
                'label' => 'Proprietário de veículo',
                'description' => 'Histórico de manutenções dos seus carros, com o selo das oficinas.',
                'icon' => 'user',
                'url' => null,
            ],
            [
                'slug' => 'oficina',
                'label' => 'Oficina',
                'description' => 'Ordens de serviço com o Selo da oficina e o perfil da sua oficina.',
                'icon' => 'wrench-screwdriver',
                'url' => null,
            ],
            [
                'slug' => null,
                'label' => 'Lojista',
                'description' => 'A loja entra com Google ou Apple, pela Área do Lojista.',
                'icon' => 'building-storefront',
                'url' => route('login.lojista'),
            ],
        ];
    }
}
