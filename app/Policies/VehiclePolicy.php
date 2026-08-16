<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
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

    public function link(User $user, Vehicle $vehicle): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        if ($this->tenantOwnsVehicle($user, $vehicle)) {
            return true;
        }

        return ! $vehicle->owners()
            ->wherePivot('is_current_owner', true)
            ->wherePivot('tenant_id', '!=', $user->tenant_id)
            ->exists();
    }

    private function tenantOwnsVehicle(User $user, Vehicle $vehicle): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        return $user->vehicles()
            ->where('vehicles.id', $vehicle->id)
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->wherePivot('tenant_id', $user->tenant_id)
            ->exists();
    }
}
