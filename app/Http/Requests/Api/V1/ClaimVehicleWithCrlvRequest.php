<?php

namespace App\Http\Requests\Api\V1;

class ClaimVehicleWithCrlvRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'crlv_verification_token' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crlv_verification_token.required' => VehicleCrlvMessages::EXPIRED,
        ];
    }
}
