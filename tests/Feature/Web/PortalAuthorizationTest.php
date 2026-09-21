<?php

namespace Tests\Feature\Web;

use App\Jobs\EmailVehicleMaintenancePdf;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->asUser()->create();
        $this->vehicle = Vehicle::factory()->create(['current_kilometers' => 50_000, 'odometer_at_registration' => 50_000]);
        $this->attachAsCurrentOwner($this->owner, $this->vehicle);
    }

    public function test_owner_can_view_own_maintenance(): void
    {
        $maintenance = $this->ownerMaintenance();

        $this->actingAs($this->owner)
            ->get(route('user.maintenances.show', $maintenance))
            ->assertOk();
    }

    public function test_user_from_another_tenant_cannot_view_maintenance(): void
    {
        $maintenance = $this->ownerMaintenance();
        $intruder = User::factory()->asUser()->create();

        $this->actingAs($intruder)
            ->get(route('user.maintenances.show', $maintenance))
            ->assertForbidden();
    }

    public function test_current_owner_can_view_maintenance_recorded_by_previous_owner(): void
    {
        $previousOwner = User::factory()->asUser()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $previousOwner->id,
            'tenant_id' => $previousOwner->tenant_id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('user.maintenances.show', $maintenance))
            ->assertOk();
    }

    public function test_garage_can_view_own_stock_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();
        $this->attachAsCurrentOwner($garage, $this->vehicle);

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk();
    }

    public function test_garage_from_another_tenant_cannot_view_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertForbidden();
    }

    public function test_garage_with_approved_consignment_grant_can_view_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();
        $this->consignmentGrant($garage, 'approved');

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk();
    }

    public function test_garage_with_pending_consignment_grant_cannot_view_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();
        $this->consignmentGrant($garage, 'pending');

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertForbidden();
    }

    public function test_user_cannot_store_maintenance_on_another_tenants_vehicle(): void
    {
        $intruder = User::factory()->asUser()->create();

        $this->actingAs($intruder)
            ->post(route('user.maintenances.store'), $this->maintenancePayload())
            ->assertForbidden();

        $this->assertDatabaseCount('maintenances', 0);
        $this->assertSame(50_000, $this->vehicle->fresh()->current_kilometers);
    }

    public function test_garage_cannot_store_maintenance_on_another_tenants_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->post(route('garage.maintenances.store'), $this->maintenancePayload())
            ->assertForbidden();

        $this->assertDatabaseCount('maintenances', 0);
        $this->assertSame(50_000, $this->vehicle->fresh()->current_kilometers);
    }

    public function test_garage_can_store_maintenance_on_own_stock_vehicle(): void
    {
        $garage = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 10_000, 'odometer_at_registration' => 10_000]);
        $this->attachAsCurrentOwner($garage, $vehicle);

        $this->actingAs($garage)
            ->post(route('garage.maintenances.store'), array_merge(
                $this->maintenancePayload(),
                ['vehicle_id' => $vehicle->id, 'kilometers' => 11_000],
            ))
            ->assertRedirect(route('garage.maintenances.index'));

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $garage->tenant_id,
        ]);
    }

    public function test_user_cannot_request_pdf_export_of_another_tenants_vehicle(): void
    {
        Queue::fake();
        $intruder = User::factory()->asUser()->create();

        $this->actingAs($intruder)
            ->post(route('user.vehicles.export-pdf', $this->vehicle))
            ->assertForbidden();

        Queue::assertNotPushed(EmailVehicleMaintenancePdf::class);
    }

    private function attachAsCurrentOwner(User $user, Vehicle $vehicle): void
    {
        $user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $user->tenant_id,
        ]);
    }

    private function ownerMaintenance(): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);
    }

    private function consignmentGrant(User $garage, string $status): void
    {
        $garage->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        VehicleAccessGrant::create([
            'user_id' => $garage->id,
            'vehicle_id' => $this->vehicle->id,
            'grant_type' => 'consignment',
            'status' => $status,
            'power_of_attorney_path' => 'procuracoes/test.pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(): array
    {
        return [
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 90_000,
            'service_category' => 'mechanical',
        ];
    }
}
