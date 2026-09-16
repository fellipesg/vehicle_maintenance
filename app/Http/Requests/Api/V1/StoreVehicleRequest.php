<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\Chassis;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Vehicle::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('chassis') && is_string($this->chassis)) {
            $this->merge([
                'chassis' => \App\Models\Vehicle::normalizeChassis($this->chassis),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $year = (int) $this->input('year', date('Y'));

        return [
            'license_plate' => 'required|string|max:10|unique:vehicles,license_plate',
            'renavam' => 'required|string|max:20|unique:vehicles,renavam',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'chassis'), new Chassis($year)],
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'required|integer|min:0|max:9999999',
            'terms_accepted' => 'required|accepted',
            'purchase_date' => 'nullable|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chassis.unique' => 'Já existe um veículo com este chassi. Você pode vinculá-lo em Vincular veículo.',
        ];
    }
}
