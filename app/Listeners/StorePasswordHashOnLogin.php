<?php

namespace App\Listeners;

use App\Http\Middleware\AuthenticateWebSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Grava o hash da senha na sessão já no login web, com a mesma chave e o mesmo formato de
 * App\Http\Middleware\AuthenticateWebSession (grupo web) e da Sanctum (chamadas do portal à API).
 *
 * Sem isto, o middleware só grava o hash na primeira requisição autenticada depois do login. Uma
 * sessão que entrou e não fez mais nada ficaria presa ao hash da senha NOVA quando a senha fosse
 * trocada, redefinida ou a conta excluída, e continuaria conectada.
 */
class StorePasswordHashOnLogin
{
    public function __construct(private AuthFactory $auth) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== AuthenticateWebSession::GUARD) {
            return;
        }

        $guard = $this->auth->guard($event->guard);
        $passwordHash = $event->user->getAuthPassword();

        if (! $guard instanceof SessionGuard || blank($passwordHash)) {
            return;
        }

        $guard->getSession()->put(AuthenticateWebSession::sessionKey(), $passwordHash);
    }
}
