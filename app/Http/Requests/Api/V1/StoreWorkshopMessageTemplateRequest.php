<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Workshop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWorkshopMessageTemplateRequest extends ApiFormRequest
{
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
            'trigger' => ['required', Rule::enum(WorkshopMessageTrigger::class)],
            'service_category' => 'nullable|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'lead_kilometers' => 'nullable|integer|min:0|max:50000',
            'min_days_since_service' => 'nullable|integer|min:1|max:3650',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
