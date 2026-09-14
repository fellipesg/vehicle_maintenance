<?php

namespace App\Http\Requests\Web\Workshop;

use App\Enums\WarrantyScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarrantyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->workshop !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'scope' => ['required', Rule::enum(WarrantyScope::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
