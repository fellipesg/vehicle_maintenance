<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class VehicleProvenanceStrip
{
    /**
     * @return list<array{maintenance_id: int, date: string|null, is_verified: bool}>
     */
    public static function segmentsForVehicle(Vehicle $vehicle): array
    {
        $maintenances = $vehicle->relationLoaded('provenanceStripMaintenances')
            ? $vehicle->provenanceStripMaintenances
            : Maintenance::query()
                ->where('vehicle_id', $vehicle->id)
                ->orderBy('maintenance_date')
                ->orderBy('id')
                ->get(['id', 'maintenance_date', 'verified_at']);

        return self::segmentsFromCollection($maintenances);
    }

    /**
     * @param  Collection<int, Maintenance>  $maintenances
     * @return list<array{maintenance_id: int, date: string|null, is_verified: bool}>
     */
    public static function segmentsFromCollection(Collection $maintenances): array
    {
        return $maintenances
            ->sortBy(fn (Maintenance $m) => ($m->maintenance_date?->format('Y-m-d') ?? '').'-'.$m->id)
            ->values()
            ->map(fn (Maintenance $m) => [
                'maintenance_id' => (int) $m->id,
                'date' => $m->maintenance_date?->toDateString(),
                'is_verified' => $m->verified_at !== null,
            ])
            ->all();
    }
}
