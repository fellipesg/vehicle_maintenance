<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Validator;

class RegisterRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // `unique:users` de propósito: a API **não** cria conta irmã neste PR. Com identidade
            // compartilhada o cadastro web aceita um e-mail que já tem outro papel (pedindo a senha
            // da identidade); mudar isto aqui exige o app mandar o portal no login, senão a pessoa
            // com dois papéis não escolhe por qual entra. Ver .ai/rules/identity.md.
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_type' => 'sometimes|in:user,workshop,garage',
            'phone' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:100',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (in_array($this->input('user_type'), ['garage', 'workshop'], true)) {
                $validator->errors()->add(
                    'user_type',
                    'Public registration is only available for vehicle owners.',
                );
            }
        });
    }
}
