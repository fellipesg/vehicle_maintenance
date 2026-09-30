<?php

namespace App\Http\Requests\Web\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dados pessoais de "Minha conta": nome, e-mail e telefone.
 *
 * Trocar o e-mail pede a senha atual: o e-mail é o login e o destino do link de redefinição, então
 * uma sessão aberta (aparelho desbloqueado ou sessão roubada) não basta para tomar a conta.
 */
class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            // Sem troca de e-mail o campo é ignorado: senha preenchida pelo gerenciador não barra
            // quem só mudou o nome ou o telefone.
            'current_password' => [
                Rule::excludeIf(fn (): bool => ! $this->changesEmail()),
                'required',
                'string',
                'current_password:web',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'name.max' => 'O nome pode ter no máximo 255 caracteres.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido, como nome@exemplo.com.',
            'email.max' => 'O e-mail pode ter no máximo 255 caracteres.',
            'email.unique' => 'Este e-mail já é usado por outra conta.',
            'phone.regex' => 'Informe o telefone com DDD, só números (10 ou 11 dígitos).',
            'current_password.required' => 'Para trocar o e-mail, informe sua senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
        ];
    }

    /**
     * O e-mail enviado é outro que o da conta (sem diferenciar maiúsculas).
     */
    public function changesEmail(): bool
    {
        return Str::lower((string) $this->input('email')) !== Str::lower((string) $this->user()?->email);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => trim((string) $this->input('email', '')),
            'phone' => $this->filled('phone')
                ? preg_replace('/\D/', '', (string) $this->input('phone'))
                : null,
        ]);
    }
}
