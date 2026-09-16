<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;
use App\Rules\Chassis;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $vehicle = Vehicle::find($this->route('id'));

        return $vehicle !== null && Gate::allows('update', $vehicle);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('chassis') && is_string($this->chassis)) {
            $this->merge([
                'chassis' => Vehicle::normalizeChassis($this->chassis),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $vehicle = Vehicle::find($id);
        $year = (int) ($this->input('year') ?? $vehicle?->year ?? date('Y'));
        $chassisMissing = $vehicle === null || $vehicle->chassis === null || $vehicle->chassis === '';

        $chassisRules = ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'chassis')->ignore($id), new Chassis($year)];
        if ($chassisMissing) {
            $chassisRules = ['required', 'string', 'max:50', Rule::unique('vehicles', 'chassis')->ignore($id), new Chassis($year)];
        }

        return [
            'license_plate' => 'sometimes|required|string|max:10|unique:vehicles,license_plate,'.$id,
            'renavam' => 'sometimes|required|string|max:20|unique:vehicles,renavam,'.$id,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => $chassisRules,
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'sometimes|required|integer|min:0|max:9999999',
            'plate_changed_at' => 'nullable|date',
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
