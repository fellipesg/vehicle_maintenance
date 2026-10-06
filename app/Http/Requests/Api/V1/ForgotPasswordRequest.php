<?php

namespace App\Http\Requests\Api\V1;

class ForgotPasswordRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail da sua conta.',
            'email.email' => 'Informe um e-mail válido, como nome@exemplo.com.',
            'email.max' => 'O e-mail pode ter no máximo 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => trim((string) $this->input('email', ''))]);
    }
}
