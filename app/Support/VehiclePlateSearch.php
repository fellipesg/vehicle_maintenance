<?php

namespace App\Support;

use App\Models\Vehicle;

class VehiclePlateSearch
{
    public static function normalize(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');
    }

    public static function findByPlate(string $plate): ?Vehicle
    {
        $normalized = self::normalize($plate);

        if ($normalized === '') {
            return null;
        }

        return Vehicle::query()
            ->where('license_plate', $normalized)
            ->orWhere('license_plate', $plate)
            ->first();
    }
}
