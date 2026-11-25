<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\ResetPasswordRequest;
use App\Http\Requests\Web\Auth\SendPasswordResetLinkRequest;
use App\Models\Identity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Recuperação de senha dos 4 portais com o Password broker nativo (tabela password_reset_tokens).
 *
 * O pedido do link responde sempre a mesma mensagem, exista ou não a conta, para não revelar quais
 * e-mails estão cadastrados. O e-mail sai em pt-BR por App\Notifications\ResetPasswordNotification.
 * A senha nova desconecta os outros aparelhos: sessões web e tokens do app.
 *
 * Quem o broker acha é a **identidade** (config/auth.php, provider `identities`), não uma conta: a
 * senha é dela e vale para todas as contas irmãs. Por isso a redefinição derruba as sessões e os
 * tokens de todos os papéis daquele e-mail, e a volta é para o login do papel único da identidade —
 * ou para o hub, quando ela tem mais de um e não há como saber por qual a pessoa vai entrar.
 */
class PasswordResetController extends Controller
{
    public const LINK_REQUESTED_MESSAGE = 'Se este e-mail estiver cadastrado, você receberá um link em alguns minutos.';

    public const PASSWORD_RESET_MESSAGE = 'Senha redefinida. Entre com a nova senha.';

    public const INVALID_LINK_MESSAGE = 'Não foi possível redefinir a senha com este link. Ele pode ter expirado ou já ter sido usado. Confira o e-mail ou peça um novo link.';

    /** Pedidos de link por e-mail e aparelho (IP) a cada 15 minutos. */
    private const LINK_ATTEMPTS_PER_EMAIL = 5;

    /** Pedidos de link por aparelho (IP), para qualquer e-mail, a cada hora. */
    private const LINK_ATTEMPTS_PER_IP = 20;

    /** Tentativas de salvar a senha nova por aparelho (IP) a cada minuto. */
    private const RESET_ATTEMPTS_PER_IP = 10;

    /**
     * @var list<string>
     */
    private const PORTALS = ['admin', 'lojista', 'usuario', 'oficina'];

    public function create(Request $request): View
    {
        $portal = (string) $request->query('portal', '');

        return view('auth.forgot-password', [
            'backUrl' => in_array($portal, self::PORTALS, true) ? route("login.{$portal}") : route('login'),
            'expiresInMinutes' => $this->linkLifetimeInMinutes(),
        ]);
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $emailKey = 'password-reset-link:'.Str::lower($email).'|'.$request->ip();
        $ipKey = 'password-reset-link-ip:'.$request->ip();

        foreach ([[$emailKey, self::LINK_ATTEMPTS_PER_EMAIL], [$ipKey, self::LINK_ATTEMPTS_PER_IP]] as [$key, $maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return back()
                    ->withInput(['email' => $email])
                    ->withErrors(['email' => $this->tooManyAttemptsMessage(RateLimiter::availableIn($key))]);
            }
        }

        RateLimiter::hit($emailKey, 15 * 60);
        RateLimiter::hit($ipKey, 60 * 60);

        // Conta inexistente, link pedido há menos de 1 minuto ou link enviado: a resposta é a mesma.
        Password::sendResetLink(['email' => $email]);

        return back()
            ->withInput(['email' => $email])
            ->with('status', self::LINK_REQUESTED_MESSAGE);
    }

    public function edit(Request $request, string $token): View
    {
        $email = trim((string) $request->query('email', ''));

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'linkExpired' => $email !== '' && ! $this->tokenIsValid($email, $token),
            'expiresInMinutes' => $this->linkLifetimeInMinutes(),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $ipKey = 'password-reset:'.$request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, self::RESET_ATTEMPTS_PER_IP)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['token' => $this->tooManyAttemptsMessage(RateLimiter::availableIn($ipKey))]);
        }

        RateLimiter::hit($ipKey, 60);

        $resetIdentity = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Identity $identity, string $password) use (&$resetIdentity): void {
                // Quem redefine costuma ter perdido o controle da conta: os tokens do app de todas
                // as contas irmãs saem, e o remember_token de cada uma é rodado. As sessões web
                // caem pelo App\Http\Middleware\AuthenticateWebSession, que compara o hash da
                // senha da identidade.
                $identity->changePasswordTo($password);

                event(new PasswordReset($identity));

                $resetIdentity = $identity;
            },
        );

        if ($status === Password::PASSWORD_RESET && $resetIdentity instanceof Identity) {
            return redirect()
                ->route($this->loginRouteAfterReset($resetIdentity))
                ->withInput(['email' => $resetIdentity->email])
                ->with('status', self::PASSWORD_RESET_MESSAGE);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['token' => self::INVALID_LINK_MESSAGE]);
    }

    /**
     * Para onde a pessoa volta depois de redefinir: o login do papel dela quando é um só, e o hub
     * quando ela tem contas irmãs — com mais de um papel, qualquer escolha nossa seria chute.
     */
    private function loginRouteAfterReset(Identity $identity): string
    {
        $portals = $identity->accessiblePortals();

        return count($portals) === 1 ? $portals[0]->loginRoute() : 'login';
    }

    private function tokenIsValid(string $email, string $token): bool
    {
        $user = Password::getUser(['email' => $email]);

        return $user !== null && Password::tokenExists($user, $token);
    }

    private function linkLifetimeInMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);
    }

    private function tooManyAttemptsMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes === 1
            ? 'Muitas tentativas seguidas. Aguarde 1 minuto e tente de novo.'
            : "Muitas tentativas seguidas. Aguarde {$minutes} minutos e tente de novo.";
    }
}
