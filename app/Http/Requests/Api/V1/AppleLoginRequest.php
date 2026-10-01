<?php

namespace App\Http\Requests\Api\V1;

class AppleLoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identity_token' => ['required', 'string', 'max:8000'],
            'nonce' => ['required', 'string', 'min:8', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'portal' => ['nullable', 'in:admin,lojista,usuario,oficina'],
        ];
    }
}
