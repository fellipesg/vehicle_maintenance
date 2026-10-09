<?php

namespace App\Http\Requests\Api\V1;

class InviteEmailRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isWorkshop();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Não foi possível enviar para este e-mail.',
            'email.email' => 'Não foi possível enviar para este e-mail.',
        ];
    }
}
