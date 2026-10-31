<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ServiceCategory;
use App\Enums\WorkshopMessageTrigger;
use App\Http\Requests\Concerns\EnforcesWorkshopMessageTemplateLimit;
use App\Models\Workshop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkshopMessageTemplateRequest extends ApiFormRequest
{
    use EnforcesWorkshopMessageTemplateLimit;

    public function authorize(): bool
    {
        $workshop = $this->route('workshop');

        return $workshop instanceof Workshop
            && Gate::allows('update', $workshop);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trigger' => ['required', Rule::enum(WorkshopMessageTrigger::class)->only(WorkshopMessageTrigger::dispatchableCases())],
            'service_category' => ['nullable', Rule::in(ServiceCategory::values())],
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'lead_kilometers' => 'nullable|integer|min:0|max:50000',
            'min_days_since_service' => 'nullable|integer|min:1|max:3650',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $workshop = $this->route('workshop');

        if ($workshop instanceof Workshop) {
            $this->addActiveMessageTemplateLimitRule($validator, $workshop);
        }
    }
}
