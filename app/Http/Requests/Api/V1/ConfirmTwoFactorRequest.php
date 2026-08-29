<?php

namespace App\Http\Requests\Api\V1;

class ConfirmTwoFactorRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
        ];
    }
}
