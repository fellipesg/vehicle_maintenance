<?php

namespace App\Http\Requests\Web\Workshop;

use App\Enums\ServiceCategory;
use App\Enums\WorkshopMessageTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isWorkshop() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trigger' => ['required', Rule::enum(WorkshopMessageTrigger::class)],
            'service_category' => ['nullable', Rule::in(ServiceCategory::values())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'lead_kilometers' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'min_days_since_service' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
