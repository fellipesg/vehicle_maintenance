<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Validator;

class UpdateProfileRequest extends ApiFormRequest
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_FIELDS = [
        'email',
        'password',
        'password_confirmation',
        'user_type',
        'is_admin',
    ];

    /**
     * O país é NOT NULL no banco (padrão Brasil). O app manda null quando o campo fica em branco;
     * sem este padrão o UPDATE estoura 23502 em vez de gravar o perfil.
     */
    protected function prepareForValidation(): void
    {
        if (! array_key_exists('country', $this->all())) {
            return;
        }

        if (blank($this->input('country'))) {
            $this->merge(['country' => 'Brasil']);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
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
            foreach (self::FORBIDDEN_FIELDS as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, "The {$field} field cannot be updated here.");
                }
            }
        });
    }
}
