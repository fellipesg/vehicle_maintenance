<?php

namespace App\Http\Requests\Web\Workshop;

use App\Http\Requests\Concerns\ValidatesVehicleInspection;
use App\Models\VehicleInspection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Emitir laudo: a autorização é a VehicleInspectionPolicy::create (oficina aprovada **e** a
 * funcionalidade ligada na conta), nunca o Pennant direto. A tela de criação explica a recusa por
 * funcionalidade desligada com Gate::inspect; aqui o envio forjado leva 403.
 */
class StoreVehicleInspectionRequest extends FormRequest
{
    use ValidatesVehicleInspection;

    public function authorize(): bool
    {
        return $this->user()?->can('create', VehicleInspection::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'license_plate' => ['required_without:chassis', 'nullable', 'string', 'max:10'],
            'chassis' => ['required_without:license_plate', 'nullable', 'string', 'max:30'],
            'new_vehicle' => ['nullable', 'array'],
            'new_vehicle.brand' => ['nullable', 'string', 'max:100'],
            'new_vehicle.model' => ['nullable', 'string', 'max:100'],
            'new_vehicle.year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
        ], $this->inspectionRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'license_plate.required_without' => 'Informe a placa ou o chassi do veículo.',
            'chassis.required_without' => 'Informe a placa ou o chassi do veículo.',
        ], $this->inspectionMessages());
    }
}
