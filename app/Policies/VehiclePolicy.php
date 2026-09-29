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

    /**
     * POST /api/v1/vehicles/{id}/link só confirma o vínculo de quem já é o dono atual. Tomar posse de
     * um veículo que já está na RevisaLog pede o CRLV-e (VehicleOwnershipService::claimExisting, no
     * assistente "Adicionar veículo"): sem prova, qualquer conta viraria dona de um veículo sem dono
     * atual (desvinculado, de conta excluída ou só em consignação) e leria chassi e RENAVAM inteiros.
     */
    public function link(User $user, Vehicle $vehicle): bool
    {
        return $this->tenantOwnsVehicle($user, $vehicle);
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
