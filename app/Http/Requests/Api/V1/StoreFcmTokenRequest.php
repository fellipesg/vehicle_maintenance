<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class StoreFcmTokenRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\UserFcmToken::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string|max:500',
            'device_type' => 'nullable|string|max:50',
        ];
    }
}
