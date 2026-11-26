<?php

namespace App\Http\Requests\Web\Signup;

use App\Rules\MatchesIdentityPassword;
use App\Support\Account\SignupFlow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Segunda etapa do cadastro em etapas: a senha.
 *
 * Dois caminhos, decididos pela busca da etapa anterior (App\Support\Account\SignupFlow):
 * - e-mail novo: a pessoa **cria** a senha, com confirmação;
 * - e-mail que já tem conta: a pessoa **informa a senha daquela conta**, porque a senha é da
 *   identidade e vale para todos os perfis dela. Quem não a souber não cria perfil nenhum — recupera
 *   a senha primeiro e volta.
 */
class StoreSignupPasswordRequest extends FormRequest
{
    /**
     * A etapa é guardada pelo middleware `signup.step`, que redireciona em vez de responder 403.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identity = SignupFlow::fromRequest($this)->identity();

        return [
            'password' => $identity === null
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['required', 'string', new MatchesIdentityPassword($identity)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Informe a senha.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }
}
