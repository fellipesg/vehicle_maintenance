<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Support\VehicleTenantResolver;

/**
 * OS que a oficina registra num carro sem proprietário atual: nasce sem tenant (tenant_id nulo) e
 * com owner_status pending, à espera da decisão do proprietário (MaintenanceOwnerDecisionService).
 * Sem dados pessoais: notas e fotos ficam como anexos pendentes, só da oficina.
 */
class OwnerlessMaintenanceService
{
    public function isOwnerless(Vehicle $vehicle): bool
    {
        return ! $vehicle->hasCurrentOwner();
    }

    /**
     * Tenant da OS: o do proprietário atual, ou nulo quando o veículo não tem proprietário.
     */
    public function tenantIdFor(Vehicle $vehicle): ?int
    {
        return $this->isOwnerless($vehicle) ? null : VehicleTenantResolver::resolveTenantId($vehicle);
    }

    public function markPending(Maintenance $maintenance): Maintenance
    {
        $maintenance->forceFill([
            'owner_status' => Maintenance::OWNER_PENDING,
            'attachments_status' => Maintenance::ATTACHMENTS_NONE,
        ])->save();

        return $maintenance;
    }

    /**
     * Quantas OS de oficina esperam decisão do proprietário atual deste veículo.
     */
    public function pendingCountForVehicle(Vehicle $vehicle): int
    {
        return Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('owner_status', Maintenance::OWNER_PENDING)
            ->count();
    }
}
