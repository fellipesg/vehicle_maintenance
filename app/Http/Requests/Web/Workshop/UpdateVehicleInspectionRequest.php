<?php

namespace App\Http\Requests\Web\Workshop;

use App\Http\Requests\Concerns\ValidatesVehicleInspection;
use App\Models\VehicleInspection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrigir o próprio laudo. A autorização é a VehicleInspectionPolicy::update: só a oficina
 * emissora, e só enquanto a conta tem a funcionalidade — é ela quem responde pelo que assinou.
 *
 * O veículo não entra: laudo não troca de carro, como a OS não troca.
 */
class UpdateVehicleInspectionRequest extends FormRequest
{
    use ValidatesVehicleInspection;

    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof VehicleInspection
            && ($this->user()?->can('update', $inspection) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->inspectionRules(), [
            'remove_report' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->inspectionMessages();
    }
}
