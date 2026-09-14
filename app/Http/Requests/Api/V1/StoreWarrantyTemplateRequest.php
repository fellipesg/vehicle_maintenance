<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WarrantyScope;
use App\Models\Workshop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWarrantyTemplateRequest extends ApiFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'scope' => ['required', Rule::enum(WarrantyScope::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
