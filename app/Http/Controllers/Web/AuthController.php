<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginHub(): View
    {
        return view('auth.login-hub');
    }

    public function showLogin(string $portal): View
    {
        abort_unless($this->portalIsValid($portal), 404);

        return view("auth.login-{$portal}", [
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
                'email' => 'Credenciais inválidas.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $this->userMatchesPortal($user, $portal)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Esta conta não tem acesso a este portal.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->dashboardRoute($portal));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'in:user,garage,workshop'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'user_type' => $data['user_type'],
            'phone' => $data['phone'] ?? null,
            'country' => 'Brasil',
        ]);

        (new TenantService())->createForUser($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(match ($user->user_type) {
            'garage' => route('garage.dashboard'),
            'workshop' => route('workshop.dashboard'),
            default => route('user.dashboard'),
        });
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function portalIsValid(string $portal): bool
    {
        return in_array($portal, ['admin', 'lojista', 'usuario'], true);
    }

    /**
     * @return array{title: string, subtitle: string, icon: string, accent: string, register: bool}
     */
    private function portalConfig(string $portal): array
    {
        return match ($portal) {
            'admin' => [
                'title' => 'Painel Administrador',
                'subtitle' => 'Acesso exclusivo para gestão da plataforma',
                'icon' => '⚙️',
                'accent' => 'wrench',
                'register' => false,
            ],
            'lojista' => [
                'title' => 'Área do Lojista',
                'subtitle' => 'Gerencie estoque e manutenções da sua loja',
                'icon' => '🏪',
                'accent' => 'emerald',
                'register' => true,
            ],
            default => [
                'title' => 'Área do Proprietário',
                'subtitle' => 'Histórico de veículos e manutenções',
                'icon' => '👤',
                'accent' => 'blue',
                'register' => true,
            ],
        };
    }

    private function userMatchesPortal(User $user, string $portal): bool
    {
        return match ($portal) {
            'admin' => $user->isAdmin(),
            'lojista' => $user->isGarage(),
            'usuario' => $user->isUser(),
            default => false,
        };
    }

    private function dashboardRoute(string $portal): string
    {
        return match ($portal) {
            'admin' => route('admin.dashboard'),
            'lojista' => route('garage.dashboard'),
            default => route('user.dashboard'),
        };
    }
}
