<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/v1/maintenances para conta que não é oficina segue VehiclePolicy::addMaintenance, como o
 * store da web: só o dono atual registra. Com o Gate view, o lojista em consignação e o admin
 * criavam uma declarada no carro dos outros, moviam o hodômetro do dono e ficavam com um registro
 * que ninguém conseguia corrigir nem apagar (a MaintenancePolicy pede o dono atual e o tenant).
 */
class ApiMaintenanceStoreOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_KILOMETERS = 60_000;

    public function test_current_owner_registers_a_maintenance(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->actingAsApiUser($owner);

        $this->postJson('/api/v1/maintenances', $this->payload($vehicle, 65_000))
            ->assertCreated()
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        $this->assertSame(65_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_dealer_with_an_active_consignment_registers_on_the_owners_vehicle(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $dealer->id,
            'tenant_id' => $dealer->tenant_id,
            'history_access_status' => 'approved',
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        $this->actingAsApiUser($dealer);
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();

        // A loja está com o carro na mão: ela registra, e o proprietário é avisado.
        $this->postJson('/api/v1/maintenances', $this->payload($vehicle, 90_000))->assertCreated();

        $this->assertSame(1, Maintenance::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertSame(90_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_admin_cannot_register_on_someone_elses_vehicle(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAsApiUser($admin);
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();

        $this->postJson('/api/v1/maintenances', $this->payload($vehicle, 150_000))->assertForbidden();

        $this->assertNothingRegistered($vehicle);
    }

    public function test_seller_cannot_register_on_the_vehicle_after_the_sale(): void
    {
        [$seller, $vehicle] = $this->ownerWithVehicle();
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $this->actingAsApiUser($seller);

        $this->postJson('/api/v1/maintenances', $this->payload($vehicle, 70_000))->assertForbidden();

        $this->assertNothingRegistered($vehicle);
    }

    private function assertNothingRegistered(Vehicle $vehicle): void
    {
        $this->assertSame(0, Maintenance::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertSame(self::CURRENT_KILOMETERS, $vehicle->fresh()->current_kilometers);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, int $kilometers): array
    {
        return [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Troca de óleo',
            'description' => 'Óleo e filtro',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => $kilometers,
            'service_category' => 'mechanical',
        ];
    }

    /**
     * @return array{User, Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => self::CURRENT_KILOMETERS,
            'odometer_at_registration' => 50_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->refresh(), $vehicle];
    }
}
