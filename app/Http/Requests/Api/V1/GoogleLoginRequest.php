<?php

namespace App\Http\Requests\Api\V1;

class GoogleLoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8000'],
            'portal' => ['nullable', 'in:admin,lojista,usuario,oficina'],
        ];
    }
}
