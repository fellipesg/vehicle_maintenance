<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class UpdateVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $vehicle = \App\Models\Vehicle::find($this->route('id'));

        return $vehicle !== null && Gate::allows('update', $vehicle);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'license_plate' => 'sometimes|required|string|max:10|unique:vehicles,license_plate,'.$id,
            'renavam' => 'sometimes|required|string|max:20|unique:vehicles,renavam,'.$id,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'sometimes|required|integer|min:0|max:9999999',
        ];
    }
}
