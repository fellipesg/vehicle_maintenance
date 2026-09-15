<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePlate;
use App\Support\VehiclePlateSearch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VehiclePlateHistoryService
{
    public function changePlate(
        Vehicle $vehicle,
        string $newPlate,
        string $source,
        ?User $by = null,
        ?CarbonInterface $effectiveDate = null,
    ): Vehicle {
        $normalized = VehiclePlateSearch::normalize($newPlate);
        $current = VehiclePlateSearch::normalize((string) $vehicle->license_plate);

        if ($normalized === '' || $normalized === $current) {
            return $vehicle;
        }

        $effective = $effectiveDate?->toDateString() ?? now()->toDateString();
        $tenantId = $by?->tenant_id;

        return DB::transaction(function () use ($vehicle, $normalized, $source, $by, $effective, $tenantId): Vehicle {
            VehiclePlate::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $effective]);

            VehiclePlate::create([
                'vehicle_id' => $vehicle->id,
                'plate' => $normalized,
                'started_at' => $effective,
                'ended_at' => null,
                'source' => $source,
                'changed_by_user_id' => $by?->id,
                'tenant_id' => $tenantId,
            ]);

            $vehicle->update(['license_plate' => $normalized]);

            return $vehicle->fresh();
        });
    }

    public function recordInitialPlate(
        Vehicle $vehicle,
        string $source,
        ?User $by = null,
    ): void {
        $normalized = VehiclePlateSearch::normalize((string) $vehicle->license_plate);

        if ($normalized === '') {
            return;
        }

        $exists = VehiclePlate::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('plate', $normalized)
            ->whereNull('ended_at')
            ->exists();

        if ($exists) {
            return;
        }

        VehiclePlate::create([
            'vehicle_id' => $vehicle->id,
            'plate' => $normalized,
            'started_at' => null,
            'ended_at' => null,
            'source' => $source,
            'changed_by_user_id' => $by?->id,
            'tenant_id' => $by?->tenant_id,
        ]);
    }
}
