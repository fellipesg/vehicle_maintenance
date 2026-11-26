<?php

namespace App\Http\Requests\Web\Signup;

use App\Enums\Portal;
use App\Http\Requests\Concerns\ValidatesWorkshopProfile;
use App\Support\Account\SignupFlow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Última etapa do cadastro em etapas: os dados do perfil escolhido.
 *
 * Proprietário pede o que o /register sempre pediu (nome, telefone e documento, os dois últimos
 * opcionais). Oficina pede os campos de App\Http\Requests\Concerns\ValidatesWorkshopProfile, os
 * mesmos do cadastro da landing — a regra de cada campo mora lá para as duas telas não divergirem.
 */
class StoreSignupDetailsRequest extends FormRequest
{
    use ValidatesWorkshopProfile;

    /**
     * A etapa é guardada pelo middleware `signup.step`, que redireciona em vez de responder 403.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->profile() === Portal::Workshop) {
            $this->normalizeWorkshopProfileInput();

            return;
        }

        foreach (['document', 'phone'] as $field) {
            if ($this->filled($field) && is_scalar($this->input($field))) {
                $this->merge([$field => preg_replace('/\D/', '', (string) $this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:255']];

        if ($this->profile() === Portal::Workshop) {
            return [...$rules, ...$this->workshopProfileRules()];
        }

        return [
            ...$rules,
            'phone' => ['nullable', 'string', 'min:10', 'max:11'],
            'document' => ['nullable', 'string', 'regex:/^(\d{11}|\d{14})$/'],
        ];
    }

    public function profile(): Portal
    {
        return SignupFlow::fromRequest($this)->profile() ?? Portal::Owner;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'document.regex' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.',
            ...$this->workshopProfileMessages(),
        ];
    }
}
