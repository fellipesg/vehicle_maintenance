<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Reautenticação do DELETE /api/v1/me, o mesmo que a tela `/conta` já exige
 * (App\Http\Requests\Web\Account\DeleteAccountRequest): um token roubado não apaga a conta sem a
 * senha atual.
 *
 * A senha é conferida aqui, e não pela regra `current_password`, porque ela resolve um guard por
 * nome e só o guard web existe em config/auth.php — o da API é o do Sanctum, registrado em
 * runtime. Mesmo motivo do UpdatePasswordRequest.
 *
 * Conta que entra só por Google/Apple nunca escolheu senha (o cadastro social grava uma aleatória,
 * ver AuthController::resolveSocialUser), então não tem como passar por aqui: o caminho dela é o
 * link assinado de /excluir-conta, e é isso que a mensagem de erro diz.
 */
class DeleteAccountRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Informe sua senha para excluir a conta.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $currentPassword = (string) $this->input('current_password', '');

            // A regra required já reclama do campo vazio.
            if ($user === null || $currentPassword === '') {
                return;
            }

            if (Hash::check($currentPassword, (string) $user->password)) {
                return;
            }

            $validator->errors()->add('current_password', $this->wrongPasswordMessage($user));
        });
    }

    /**
     * Quem entrou por provedor social pode nunca ter escolhido senha; mandar só "a senha não
     * confere" deixaria a pessoa sem saída numa tela de exclusão de conta. A conta criada pelo
     * provedor e a que apenas vinculou um depois são indistinguíveis aqui, então a mensagem
     * serve às duas: diz o que houve e aponta o caminho sem senha.
     */
    private function wrongPasswordMessage(User $user): string
    {
        if (blank($user->provider)) {
            return 'A senha atual não confere.';
        }

        return 'A senha atual não confere. Se você entra pelo '.Str::ucfirst((string) $user->provider)
            .', peça o link de confirmação em '.route('account-deletion.show').' ou defina uma senha antes.';
    }
}
