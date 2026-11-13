<?php

namespace App\Http\Requests\Web\Workshop;

use App\Enums\ServiceCategory;
use App\Enums\WorkshopMessageTrigger;
use App\Http\Requests\Concerns\EnforcesWorkshopMessageTemplateLimit;
use App\Models\WorkshopMessageTemplate;
use App\Rules\KnownMessageTemplateTags;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMessageTemplateRequest extends FormRequest
{
    use EnforcesWorkshopMessageTemplateLimit;

    public function authorize(): bool
    {
        return $this->user()?->isWorkshop() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $template = $this->route('message_template');
        $stored = $template instanceof WorkshopMessageTemplate ? $template : null;

        return [
            'trigger' => ['required', Rule::enum(WorkshopMessageTrigger::class)->only(WorkshopMessageTrigger::dispatchableCases())],
            'service_category' => ['nullable', Rule::in(ServiceCategory::values())],
            'title' => ['required', 'string', 'max:255', new KnownMessageTemplateTags($stored?->title)],
            'body' => ['required', 'string', 'max:5000', new KnownMessageTemplateTags($stored?->body)],
            'lead_kilometers' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'min_days_since_service' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $workshop = $this->user()?->workshop;
        $template = $this->route('message_template');

        if ($workshop !== null && $template instanceof WorkshopMessageTemplate) {
            $this->addActiveMessageTemplateLimitRule($validator, $workshop, $template);
            $this->addMessageTemplateTriggerPlanRule($validator, $workshop, $template);
        }
    }
}
