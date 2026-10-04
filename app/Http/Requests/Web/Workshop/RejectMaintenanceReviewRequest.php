<?php

namespace App\Http\Requests\Web\Workshop;

use Illuminate\Foundation\Http\FormRequest;

class RejectMaintenanceReviewRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => 'motivo',
        ];
    }
}
