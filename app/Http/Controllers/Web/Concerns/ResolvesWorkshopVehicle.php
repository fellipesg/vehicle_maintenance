<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Rules\Chassis;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Qual veículo a oficina está registrando — a etapa 1 do lado do POST, irmã de
 * App\Support\Workshop\WorkshopVehicleStep (que é o lado do GET). Usada pela OS
 * (Workshop\MaintenanceController) e pela vistoria cautelar (Workshop\InspectionController).
 *
 * Pela placa, o veículo tem de existir. Pelo chassi, **veículo com proprietário é recusado**: os 17
 * caracteres valem como posse do carro para cadastrar o que ainda não existe, não como passe para
 * anexar registro ao histórico de quem tem conta — esse caminho é a placa (.ai/rules/public-lookup.md).
 * Chassi que não resolve nada vira veículo novo, e aí marca, modelo e ano passam a ser obrigatórios.
 */
trait ResolvesWorkshopVehicle
{
    /**
     * @param  array<string, mixed>  $data  o que a validação do formulário já aprovou
     * @param  callable(string): string  $notFoundMessage  mensagem da placa sem cadastro
     * @return array{vehicle: Vehicle|null, newVehicle: array<string, mixed>|null, chassis: string}
     *
     * @throws ValidationException
     */
    protected function resolveWorkshopVehicle(
        Request $request,
        array $data,
        string $chassisOwnedMessage,
        callable $notFoundMessage,
    ): array {
        $chassis = Vehicle::normalizeChassis((string) ($data['chassis'] ?? ''));
        $newVehicle = null;

        if ($chassis === '') {
            $licensePlate = VehiclePlateSearch::normalize((string) ($data['license_plate'] ?? ''));
            $vehicle = VehiclePlateSearch::findByPlate($licensePlate)?->vehicle;

            if ($vehicle === null) {
                throw ValidationException::withMessages([
                    'license_plate' => $notFoundMessage($licensePlate),
                ]);
            }

            return ['vehicle' => $vehicle, 'newVehicle' => null, 'chassis' => ''];
        }

        $vehicle = Vehicle::findByChassis($chassis);

        if ($vehicle !== null && $vehicle->hasCurrentOwner()) {
            throw ValidationException::withMessages([
                'chassis' => $chassisOwnedMessage,
            ]);
        }

        if ($vehicle === null) {
            $newVehicle = $request->validate([
                'chassis' => ['required', 'string', new Chassis(isset($data['new_vehicle']['year']) ? (int) $data['new_vehicle']['year'] : null)],
                'new_vehicle.brand' => ['required', 'string', 'max:100'],
                'new_vehicle.model' => ['required', 'string', 'max:100'],
                'new_vehicle.year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            ], [
                'new_vehicle.brand.required' => 'Informe a marca do veículo.',
                'new_vehicle.model.required' => 'Informe o modelo do veículo.',
                'new_vehicle.year.required' => 'Informe o ano do veículo.',
            ])['new_vehicle'];
        }

        return ['vehicle' => $vehicle, 'newVehicle' => $newVehicle, 'chassis' => $chassis];
    }
}
