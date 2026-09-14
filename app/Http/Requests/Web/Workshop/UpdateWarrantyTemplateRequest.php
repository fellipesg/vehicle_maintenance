<?php

namespace App\Http\Requests\Web\Workshop;

use App\Enums\WarrantyScope;
use App\Http\Requests\Concerns\ValidatesWarrantyTemplateImmutability;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWarrantyTemplateRequest extends FormRequest
{
    use ValidatesWarrantyTemplateImmutability;

    public function authorize(): bool
    {
        $workshop = $this->user()?->workshop;
        $template = $this->route('warranty_template');

        return $workshop !== null
            && $template instanceof WarrantyTemplate
            && $template->workshop_id === $workshop->id;
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
        $workshop = $this->user()?->workshop;
        $template = $this->route('warranty_template');

        if ($workshop === null || ! $template instanceof WarrantyTemplate) {
            return;
        }

        $this->addWarrantyTemplateImmutabilityRules($validator, $workshop, $template);
    }
}
