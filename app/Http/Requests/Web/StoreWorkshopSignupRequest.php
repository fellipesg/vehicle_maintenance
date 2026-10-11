<?php

namespace App\Http\Requests\Web;

use App\Http\Controllers\Web\Workshop\ProfileController;
use App\Rules\ValidCnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro próprio da oficina (/para-oficinas/cadastro): a pessoa responsável, a oficina e o endereço.
 */
class StoreWorkshopSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CNPJ, CEP e telefone aceitam pontuação; o resto da requisição só enxerga os dígitos.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['cnpj', 'cep', 'phone'] as $field) {
            if ($this->filled($field) && is_scalar($this->input($field))) {
                $normalized[$field] = preg_replace('/\D/', '', (string) $this->input($field));
            }
        }

        if ($this->filled('state') && is_scalar($this->input('state'))) {
            $normalized['state'] = strtoupper(trim((string) $this->input('state')));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new ValidCnpj, 'unique:workshops,cnpj'],
            'phone' => ['required', 'string', 'digits_between:10,11'],
            'cep' => ['required', 'string', 'size:8'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2', Rule::in(array_keys(ProfileController::STATES))],
            'ref' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'phone.digits_between' => 'Informe o WhatsApp ou telefone com DDD (10 ou 11 dígitos).',
            'cep.size' => 'Informe o CEP com 8 dígitos.',
            'state.in' => 'Escolha a UF da lista.',
        ];
    }
}
