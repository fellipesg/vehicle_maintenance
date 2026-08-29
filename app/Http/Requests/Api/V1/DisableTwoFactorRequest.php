<?php

namespace App\Http\Requests\Api\V1;

class DisableTwoFactorRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ];
    }
}
