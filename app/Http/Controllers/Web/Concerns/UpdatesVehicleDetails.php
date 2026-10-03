<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Rules\Chassis;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\Vehicle\VehiclePlateHistoryService;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Formulário "Editar veículo" (campos de user.vehicles._form e as duas capas, 16:9 e 9:16, recortadas
 * no navegador por <x-ui.image-cropper>). Quem chama autoriza antes (VehiclePolicy::update) e decide
 * para onde voltar.
 *
 * O chassi é obrigatório: veículo legado sem chassi o recebe na primeira edição
 * (.ai/rules/migrations.md). Placa, chassi e RENAVAM são normalizados antes da validação, como no
 * cadastro (RegistersVehicleWithOwnership::registerVehicle), para que o unique compare o valor que
 * será gravado: "9bwzzz377vt004251" já em uso vira erro no campo, não violação do índice. Quilometragem
 * atual: nunca abaixo da maior quilometragem já registrada numa manutenção; um valor menor que a do
 * cadastro corrige também o cadastro (erro de digitação na entrada).
 */
trait UpdatesVehicleDetails
{
    protected function updateVehicleDetails(Request $request, Vehicle $vehicle, VehicleCoverService $covers): void
    {
        $request->merge([
            'license_plate' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('license_plate')) ?? ''),
            'renavam' => preg_replace('/\D/', '', (string) $request->input('renavam')) ?? '',
            'chassis' => Vehicle::normalizeChassis((string) $request->input('chassis')),
        ]);

        $kilometerFloor = (int) $vehicle->maintenances()->max('kilometers');
        $coverRules = File::image(allowSvg: false)
            ->types(['jpg', 'jpeg', 'png', 'webp'])
            ->max(VehicleEntryFlow::COVER_MAX_KILOBYTES);

        $data = $request->validate([
            'license_plate' => ['required', 'string', 'max:10', Rule::unique('vehicles', 'license_plate')->ignore($vehicle->id)],
            'renavam' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'renavam')->ignore($vehicle->id)],
            'crv_number' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'chassis')->ignore($vehicle->id), new Chassis((int) $request->input('year', $vehicle->year))],
            'motorization' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:50'],
            'current_kilometers' => ['nullable', 'integer', 'min:'.$kilometerFloor, 'max:9999999'],
            'cover' => ['nullable', $coverRules],
            'cover_portrait' => ['nullable', $coverRules],
        ], [
            'current_kilometers.min' => 'A quilometragem atual não pode ser menor que a da última manutenção registrada ('.number_format($kilometerFloor, 0, ',', '.').' km).',
        ]);

        $newPlate = (string) $data['license_plate'];
        $previousPlate = (string) $vehicle->license_plate;
        $kilometers = $data['current_kilometers'] ?? null;

        unset($data['cover'], $data['cover_portrait'], $data['license_plate'], $data['current_kilometers']);

        if ($kilometers !== null) {
            $data['current_kilometers'] = (int) $kilometers;

            if ($vehicle->odometer_at_registration !== null && (int) $kilometers < (int) $vehicle->odometer_at_registration) {
                $data['odometer_at_registration'] = (int) $kilometers;
            }
        }

        $vehicle->update($data);

        if (strtoupper($newPlate) !== strtoupper($previousPlate)) {
            app(VehiclePlateHistoryService::class)->changePlate($vehicle->fresh(), $newPlate, 'manual', $request->user());
        }

        if ($request->file('cover') !== null) {
            $covers->storeLandscape($vehicle, $request->file('cover'));
        }

        if ($request->file('cover_portrait') !== null) {
            $covers->storePortrait($vehicle, $request->file('cover_portrait'));
        }
    }
}
