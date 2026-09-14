<?php

namespace App\Http\Requests\Api\V1;

class LoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
            'portal' => 'nullable|in:admin,lojista,usuario,oficina',
        ];
    }
}
