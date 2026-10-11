<?php

namespace Tests\Feature\Concerns;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Fixtures de OS de oficina em carro sem proprietário (chassi, sem placa nem RENAVAM).
 */
trait BuildsOwnerlessRecords
{
    protected const OWNERLESS_CHASSIS = '9BWZZZ377VT004251';

    protected function workshopAccount(): User
    {
        return User::factory()->asWorkshop()->create()->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function ownerlessVehicle(array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create($attributes + [
            'license_plate' => null,
            'renavam' => null,
            'chassis' => self::OWNERLESS_CHASSIS,
            'brand' => 'Fiat',
            'model' => 'Argo',
            'year' => 2021,
            'current_kilometers' => 40_000,
            'odometer_at_registration' => 40_000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function ownerlessRecord(User $workshopUser, Vehicle $vehicle, array $attributes = []): Maintenance
    {
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create($attributes + [
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshopUser->workshop->id,
            'user_id' => $workshopUser->id,
            'maintenance_type' => 'Troca de óleo',
            'description' => 'Cliente Maria CPF 123.456.789-09 pediu urgência',
            'kilometers' => 50_000,
        ]);

        $maintenance->forceFill([
            'tenant_id' => null,
            'owner_status' => Maintenance::OWNER_PENDING,
            'attachments_status' => Maintenance::ATTACHMENTS_NONE,
        ])->save();

        MaintenanceItem::create([
            'maintenance_id' => $maintenance->id,
            'name' => 'Filtro de óleo',
            'quantity' => 1,
            'unit_price' => 55.9,
            'total_price' => 55.9,
        ]);

        return $maintenance->fresh();
    }

    protected function withPendingAttachments(Maintenance $maintenance): Maintenance
    {
        Invoice::factory()->create(['maintenance_id' => $maintenance->id, 'file_path' => 'invoices/pendente.pdf']);
        MaintenancePhoto::factory()->create(['maintenance_id' => $maintenance->id, 'created_by' => $maintenance->user_id]);

        return $maintenance->fresh();
    }

    protected function ownerOf(Vehicle $vehicle, bool $verified = true): User
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
            'ownership_verified_at' => $verified ? now() : null,
            'ownership_type' => 'owner',
        ]);

        return $owner;
    }
}
