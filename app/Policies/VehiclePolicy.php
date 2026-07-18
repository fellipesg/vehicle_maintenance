<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle);
    }

    public function viewMaintenances(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle);
    }

    private function tenantOwnsVehicle(User $user, Vehicle $vehicle): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        return $user->vehicles()
            ->where('vehicles.id', $vehicle->id)
            ->wherePivot('is_current_owner', true)
            ->wherePivot('tenant_id', $user->tenant_id)
            ->exists();
    }
}
