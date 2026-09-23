<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleMileageService
{
    /**
     * @throws ValidationException
     */
    public function assertMaintenanceKilometers(Vehicle $vehicle, int $kilometers): void
    {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                'kilometers' => 'A quilometragem deve ser zero ou maior.',
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
}
