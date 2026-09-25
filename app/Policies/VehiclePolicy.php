<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;

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
            || $this->hasActiveConsignment($user, $vehicle);
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
            || $this->hasActiveConsignment($user, $vehicle);
    }

    /**
     * Reading the history the vehicle already had belongs to its owner, not to whoever is
     * selling it. A consigning garage only gets it once the owner approves, or staff
     * approve the power of attorney it uploaded.
     */
    public function viewFullHistory(User $user, Vehicle $vehicle): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->tenantOwnsVehicle($user, $vehicle)) {
            return true;
        }

        return $this->consignmentFor($user, $vehicle)?->grantsHistoryAccess() === true;
    }

    /**
     * Registering a maintenance moves the vehicle odometer, so it is limited to whoever
     * physically holds the car: the current owner, or the garage that declared it on
     * consignment.
     */
    public function addMaintenance(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle)
            || $this->hasActiveConsignment($user, $vehicle);
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

    public function endConsignment(User $user, Vehicle $vehicle): bool
    {
        return $this->hasActiveConsignment($user, $vehicle);
    }

    private function hasActiveConsignment(User $user, Vehicle $vehicle): bool
    {
        return $this->consignmentFor($user, $vehicle) !== null;
    }

    private function consignmentFor(User $user, Vehicle $vehicle): ?VehicleConsignment
    {
        if (! $user->isGarage()) {
            return null;
        }

        return VehicleConsignment::query()
            ->active()
            ->where('garage_user_id', $user->id)
            ->where('vehicle_id', $vehicle->id)
            ->first();
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
