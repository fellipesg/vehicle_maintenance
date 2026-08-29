<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class StoreVehicleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Vehicle::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'license_plate' => 'required|string|max:10|unique:vehicles,license_plate',
            'renavam' => 'required|string|max:20|unique:vehicles,renavam',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'required|integer|min:0|max:9999999',
            'terms_accepted' => 'required|accepted',
            'purchase_date' => 'nullable|date',
        ];
    }
}
