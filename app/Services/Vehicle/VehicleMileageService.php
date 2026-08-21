<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleMileageService
{
    /**
     * @throws ValidationException
     */
    public function assertMaintenanceKilometers(Vehicle $vehicle, int $kilometers, ?Maintenance $except = null): void
    {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                'kilometers' => 'A quilometragem deve ser zero ou maior.',
            ]);
        }

        $floor = $this->minimumAllowedKilometers($vehicle, $except);

        if ($kilometers < $floor) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no mínimo {$floor} km (hodômetro atual ou última manutenção registrada).",
            ]);
        }
    }

    public function registerOdometer(Vehicle $vehicle, int $kilometers): void
    {
        $vehicle->update([
            'current_kilometers' => $kilometers,
            'odometer_at_registration' => $kilometers,
        ]);
    }

    public function applyMaintenanceKilometers(Vehicle $vehicle, int $kilometers): void
    {
        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->odometer_at_registration ?? 0),
                (int) $vehicle->maintenances()->max('kilometers'),
                $kilometers,
            ),
        ]);
    }

    public function refreshCurrentKilometers(Vehicle $vehicle): void
    {
        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->odometer_at_registration ?? 0),
                (int) $vehicle->maintenances()->max('kilometers'),
            ),
        ]);
    }

    private function minimumAllowedKilometers(Vehicle $vehicle, ?Maintenance $except): int
    {
        $query = $vehicle->maintenances()->whereNotNull('kilometers');

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        $maxMaintenance = (int) ($query->max('kilometers') ?? 0);
        $registration = (int) ($vehicle->odometer_at_registration ?? $vehicle->current_kilometers ?? 0);

        return max($registration, $maxMaintenance);
    }
}
