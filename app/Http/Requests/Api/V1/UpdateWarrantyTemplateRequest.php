<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WarrantyScope;
use App\Http\Requests\Concerns\ValidatesWarrantyTemplateImmutability;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWarrantyTemplateRequest extends ApiFormRequest
{
    use ValidatesWarrantyTemplateImmutability;

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'duration_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
            'scope' => ['sometimes', 'required', Rule::enum(WarrantyScope::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $workshop = $this->route('workshop');
        $template = $this->route('warranty_template');

        if (! $workshop instanceof Workshop || ! $template instanceof WarrantyTemplate) {
            return;
        }

        $this->addWarrantyTemplateImmutabilityRules($validator, $workshop, $template);
    }
}
