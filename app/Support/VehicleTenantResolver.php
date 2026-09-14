<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class VehicleTenantResolver
{
    public static function resolveTenantId(Vehicle $vehicle): ?int
    {
        $tenantId = DB::table('user_vehicles')
            ->where('vehicle_id', $vehicle->id)
            ->where('is_current_owner', true)
            ->whereNotNull('tenant_id')
            ->value('tenant_id');

        if ($tenantId !== null) {
            return (int) $tenantId;
        }

        $fallback = DB::table('user_vehicles')
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('tenant_id')
            ->orderByDesc('is_current_owner')
            ->value('tenant_id');

        return $fallback !== null ? (int) $fallback : null;
    }
}
