<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;

class LinkVehicleRequest extends ApiFormRequest
{
    /**
     * Authorize here (not in the controller) so a vehicle owned by another tenant
     * answers 403 instead of leaking a 422 about the document fields.
     */
    public function authorize(): bool
    {
        $vehicle = $this->vehicle();

        return $vehicle === null || Gate::allows('link', $vehicle);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $proof = $this->alreadyCurrentOwner() ? 'nullable' : 'required';

        return [
            'license_plate' => [$proof, 'string', 'max:10'],
            'renavam' => [$proof, 'string', 'max:20'],
            'purchase_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'license_plate.required' => 'Informe a placa que consta no documento do veículo.',
            'renavam.required' => 'Informe o RENAVAM que consta no documento do veículo.',
        ];
    }

    public function vehicle(): ?Vehicle
    {
        return Vehicle::find($this->route('id'));
    }

    /**
     * A user who already owns the vehicle gains nothing from linking it again,
     * so the document fields are optional for them.
     */
    public function alreadyCurrentOwner(): bool
    {
        $vehicle = $this->vehicle();
        $user = $this->user();

        if ($vehicle === null || $user === null) {
            return false;
        }

        return $user->vehicles()
            ->where('vehicle_id', $vehicle->id)
            ->wherePivot('is_current_owner', true)
            ->exists();
    }
}
