<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;

class VehicleEstimatedKmService
{
    public function estimate(Vehicle $vehicle): int
    {
        $lastMaintenance = $vehicle->maintenances
            ->filter(fn (Maintenance $maintenance) => $maintenance->kilometers !== null)
            ->sortByDesc(fn (Maintenance $maintenance) => $maintenance->maintenance_date?->format('Y-m-d').str_pad((string) $maintenance->id, 10, '0', STR_PAD_LEFT))
            ->first();

        if ($lastMaintenance === null) {
            if ($vehicle->current_kilometers !== null) {
                return (int) $vehicle->current_kilometers;
            }

            if ($vehicle->odometer_at_registration !== null) {
                return (int) $vehicle->odometer_at_registration;
            }

            return 0;
        }

        $anchorKm = (int) $lastMaintenance->kilometers;
        $daysElapsed = $lastMaintenance->maintenance_date?->diffInDays(now()) ?? 0;
        $dailyKm = $this->dailyKilometers();

        return (int) round($anchorKm + ($daysElapsed * $dailyKm));
    }

    public function dailyKilometers(): float
    {
        $averagePerYear = max(1, (int) config('maintenance_intervals.average_kilometers_per_year', 13_000));

        return $averagePerYear / 365;
    }
}
