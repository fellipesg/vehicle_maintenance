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
        if ($user->isAdmin()) {
            return true;
        }

        return $this->tenantOwnsVehicle($user, $vehicle)
            || $this->hasApprovedConsignmentGrant($user, $vehicle);
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
        return $this->tenantOwnsVehicle($user, $vehicle)
            || $this->hasApprovedConsignmentGrant($user, $vehicle);
    }

    /**
     * Registering a maintenance also moves the vehicle odometer, so only the current owner may do it.
     */
    public function addMaintenance(User $user, Vehicle $vehicle): bool
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

    /**
     * Garages selling a vehicle on consignment get read access once staff approve the power of attorney.
     */
    private function hasApprovedConsignmentGrant(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->accessGrants()
            ->where('user_id', $user->id)
            ->where('grant_type', 'consignment')
            ->where('status', 'approved')
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
