<?php

namespace App\Support;

use App\Models\Vehicle;
use App\Models\VehiclePlate;
use App\Support\Vehicle\VehicleLookupResult;

class VehiclePlateSearch
{
    public static function normalize(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');
    }

    public static function findByPlate(string $plate): ?VehicleLookupResult
    {
        $normalized = self::normalize($plate);

        if ($normalized === '') {
            return null;
        }

        $vehicle = Vehicle::query()
            ->where('license_plate', $normalized)
            ->orWhere('license_plate', $plate)
            ->first();

        if ($vehicle !== null) {
            return new VehicleLookupResult($vehicle, VehicleLookupResult::MATCH_CURRENT_PLATE);
        }

        $historical = VehiclePlate::query()
            ->where('plate', $normalized)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->first();

        if ($historical !== null) {
            $vehicle = $historical->vehicle;

            return new VehicleLookupResult(
                $vehicle,
                VehicleLookupResult::MATCH_PREVIOUS_PLATE,
                $historical->ended_at,
            );
        }

        return null;
    }

    public static function findByIdentifier(string $identifier): ?VehicleLookupResult
    {
        $trimmed = trim($identifier);

        if ($trimmed === '') {
            return null;
        }

        $chassisNormalized = Vehicle::normalizeChassis($trimmed);
        if (strlen($chassisNormalized) >= 9) {
            $byChassis = Vehicle::findByChassis($chassisNormalized);
            if ($byChassis !== null) {
                return new VehicleLookupResult($byChassis, VehicleLookupResult::MATCH_CHASSIS);
            }
        }

        $renavamDigits = preg_replace('/\D/', '', $trimmed) ?? '';
        if (strlen($renavamDigits) === 11) {
            $byRenavam = Vehicle::findByRenavam($renavamDigits);
            if ($byRenavam !== null) {
                return new VehicleLookupResult($byRenavam, VehicleLookupResult::MATCH_RENAVAM);
            }
        }

        return self::findByPlate($trimmed);
    }
}
