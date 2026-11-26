<?php

namespace App\Http\Requests\Concerns;

use App\Rules\ValidCnpj;
use App\Support\BrazilianStates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Campos da oficina (nome fantasia, CNPJ, contato e endereço), compartilhados pelas duas entradas
 * que criam uma: o cadastro da landing (/para-oficinas/cadastro) e a última etapa do cadastro em
 * etapas (/cadastro/dados).
 *
 * Fica num concern porque uma regra de campo divergir entre as duas telas é um bug silencioso: a
 * mesma oficina passaria numa e não na outra. Quem muda um campo muda aqui, e as duas acompanham.
 */
trait ValidatesWorkshopProfile
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function workshopProfileRules(): array
    {
        return [
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new ValidCnpj, 'unique:workshops,cnpj'],
            'phone' => ['required', 'string', 'digits_between:10,11'],
            'cep' => ['required', 'string', 'size:8'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'ref' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function workshopProfileMessages(): array
    {
        return [
            'phone.digits_between' => 'Informe o WhatsApp ou telefone com DDD (10 ou 11 dígitos).',
            'cep.size' => 'Informe o CEP com 8 dígitos.',
            'state.in' => 'Escolha a UF da lista.',
        ];
    }

    /**
     * CNPJ, CEP e telefone aceitam pontuação; o resto da requisição só enxerga os dígitos.
     */
    protected function normalizeWorkshopProfileInput(): void
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
}
