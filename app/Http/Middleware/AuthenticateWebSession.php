<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessão web presa ao hash da senha (grupo web, bootstrap/app.php): trocar ou redefinir a senha (e
 * excluir a conta, que também a troca) desconecta os outros aparelhos; quem trocou continua,
 * porque o hash novo é gravado no fim da própria requisição.
 *
 * Difere do Illuminate\Session\Middleware\AuthenticateSession em dois pontos:
 * - grava o hash como está no banco, o formato que o Laravel\Sanctum\Http\Middleware\
 *   AuthenticateSession compara nas chamadas do portal à API com a sessão
 *   (EnsureFrontendRequestsAreStateful). O do framework grava um HMAC do hash, e a Sanctum
 *   derrubaria a sessão na primeira chamada à API. A validação herdada aceita os dois formatos;
 * - usa sempre o guard web (o da sessão, config sanctum.guard), e não o guard padrão do momento.
 *
 * O mesmo valor é gravado já no login por App\Listeners\StorePasswordHashOnLogin.
 */
class AuthenticateWebSession extends AuthenticateSession
{
    public const GUARD = 'web';

    /**
     * Chave da sessão com o hash da senha, a mesma da Sanctum (password_hash_{guard}).
     */
    public static function sessionKey(): string
    {
        return 'password_hash_'.self::GUARD;
    }

    /**
     * @param  Request  $request
     */
    public function handle($request, Closure $next): Response
    {
        $guard = $this->guard();
        $user = $request->hasSession() && $guard instanceof SessionGuard ? $guard->user() : null;

        if ($user === null || blank($user->getAuthPassword())) {
            return $next($request);
        }

        if ($guard->viaRemember()) {
            $passwordHashFromCookie = explode('|', (string) $request->cookies->get($guard->getRecallerName()))[2] ?? null;

            if (! $passwordHashFromCookie || ! $this->validatePasswordHash($user->getAuthPassword(), $passwordHashFromCookie)) {
                $this->logout($request);
            }
        }

        if (! $request->session()->has(self::sessionKey())) {
            $this->storePasswordHashInSession($request);
        }

        if (! $this->validatePasswordHash($user->getAuthPassword(), (string) $request->session()->get(self::sessionKey()))) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request, $guard): void {
            if ($guard->hasUser()) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    /**
     * @param  Request  $request
     */
    protected function storePasswordHashInSession($request): void
    {
        $user = $this->guard()->user();

        if ($user === null) {
            return;
        }

        $request->session()->put(self::sessionKey(), $user->getAuthPassword());
    }

    protected function guard(): Guard
    {
        return $this->auth->guard(self::GUARD);
    }
}
