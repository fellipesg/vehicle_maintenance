<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * App\Services\User\DeleteUserAccount apaga as linhas de `sessions` da pessoa, o que derruba os
 * outros aparelhos. A sessão da própria requisição não morre assim: o framework a grava de novo
 * ao fim da resposta, e o App\Http\Middleware\AuthenticateWebSession regrava o hash da senha nova
 * logo em seguida — o navegador que pediu a exclusão continuaria autenticado sobre a linha
 * anonimizada. Por isso o logout explícito aqui, em todo caminho que possa ter sessão web
 * (/conta, o link assinado de /excluir-conta e a chamada à API pelo portal, que é stateful).
 */
trait EndsDeletedAccountSession
{
    protected function endWebSessionOf(Request $request, User $deleted): void
    {
        if (Auth::guard('web')->id() !== $deleted->getKey()) {
            return;
        }

        Auth::guard('web')->logout();

        if (! $request->hasSession()) {
            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
