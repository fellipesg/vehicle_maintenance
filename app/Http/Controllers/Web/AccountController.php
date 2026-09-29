<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Account\DeleteAccountRequest;
use App\Http\Requests\Web\Account\UpdateAccountPasswordRequest;
use App\Http\Requests\Web\Account\UpdateAccountRequest;
use App\Notifications\AccountEmailChangedNotification;
use App\Services\User\DeleteUserAccount;
use App\Support\PortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Minha conta" (/conta), comum aos 4 portais: dados pessoais, troca de senha e exclusão da conta.
 *
 * A exclusão anonimiza a conta (App\Services\User\DeleteUserAccount, regra .ai/rules/user.md): os
 * veículos e todas as manutenções do chassi continuam, só os dados pessoais e os vínculos saem.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('account.edit', [
            'user' => $user,
            'accountLabel' => PortalAccess::accountLabel($user),
            'homeUrl' => PortalAccess::homeUrl($user),
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $previousEmail = (string) $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        // A troca pediu a senha atual (UpdateAccountRequest); o endereço antigo ainda é avisado.
        if ($emailChanged) {
            Notification::route('mail', $previousEmail)
                ->notify(new AccountEmailChangedNotification((string) $user->name, (string) $user->email));
        }

        return redirect()->route('account.edit')->with('success', 'Dados da conta atualizados.');
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        // A senha nova derruba a sessão dos outros aparelhos (App\Http\Middleware\AuthenticateWebSession)
        // e o novo remember_token desliga o "Lembrar de mim" deles; esta sessão continua.
        $request->user()->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('account.edit')->with('success', 'Senha alterada. Use a senha nova na próxima vez que entrar.');
    }

    public function destroy(DeleteAccountRequest $request, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $deleteUserAccount->handle($request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Sua conta foi excluída. O histórico de manutenções continua ligado ao chassi de cada veículo.');
    }
}
