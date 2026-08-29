<?php

namespace App\Http\Requests\Api\V1;

class ChallengeTwoFactorRequest extends ApiFormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => 'required|string',
            'code' => 'required_without:recovery_code|nullable|string|size:6',
            'recovery_code' => 'required_without:code|nullable|string',
        ];
    }
}
