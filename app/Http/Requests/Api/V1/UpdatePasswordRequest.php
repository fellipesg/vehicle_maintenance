<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Troca de senha com o usuário logado. A senha atual é conferida aqui, e não pela regra
 * current_password, porque ela resolve um guard por nome e só o guard web existe em
 * config/auth.php — o da API é o do Sanctum, registrado em runtime.
 */
class UpdatePasswordRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Informe sua senha atual.',
            'password.required' => 'Crie uma senha nova.',
            'password.min' => 'A senha nova deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha nova não confere.',
            'password.different' => 'A senha nova precisa ser diferente da atual.',
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

            if (! Hash::check($currentPassword, (string) $user->password)) {
                $validator->errors()->add('current_password', 'A senha atual não confere.');
            }
        });
    }
}
