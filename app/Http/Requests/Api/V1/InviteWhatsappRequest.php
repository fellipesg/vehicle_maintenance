<?php

namespace App\Http\Requests\Api\V1;

class InviteWhatsappRequest extends ApiFormRequest
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
        return ['phone' => ['required', 'string', 'max:30']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.required' => 'Digite o telefone do cliente com DDD.'];
    }
}
