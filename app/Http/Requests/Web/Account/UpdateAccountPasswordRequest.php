<?php

namespace App\Http\Requests\Web\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Troca de senha em "Minha conta". Erros no bag updatePassword, para não misturar com os dados
 * pessoais e a exclusão, que ficam na mesma página.
 */
class UpdateAccountPasswordRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'updatePassword';

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
            'current_password' => ['required', 'string', 'current_password:web'],
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
            'current_password.current_password' => 'A senha atual não confere.',
            'password.required' => 'Crie uma senha nova.',
            'password.min' => 'A senha nova deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha nova não confere.',
            'password.different' => 'A senha nova precisa ser diferente da atual.',
        ];
    }
}
