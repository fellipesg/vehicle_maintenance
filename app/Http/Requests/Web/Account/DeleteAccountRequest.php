<?php

namespace App\Http\Requests\Web\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Exclusão da conta em "Minha conta": pede a senha atual. Erros no bag deleteAccount.
 */
class DeleteAccountRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'deleteAccount';

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
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Informe sua senha para excluir a conta.',
            'password.current_password' => 'A senha não confere.',
        ];
    }
}
