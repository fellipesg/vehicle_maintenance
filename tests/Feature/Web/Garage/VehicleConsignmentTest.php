<?php

namespace Tests\Feature\Web\Garage;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleConsignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $garage;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
        $this->owner = User::factory()->asUser()->create();
        $this->vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50_000,
            'odometer_at_registration' => 50_000,
        ]);

        $this->owner->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->owner->tenant_id,
        ]);
    }

    public function test_consigning_garage_can_register_maintenance(): void
    {
        $this->consign();

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->maintenancePayload())
            ->assertRedirect(route('garage.maintenances.index'));

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $this->vehicle->id,
            'tenant_id' => $this->garage->tenant_id,
            'registered_by_type' => 'garage',
        ]);
    }

    public function test_garage_without_consignment_cannot_register_maintenance(): void
    {
        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->maintenancePayload())
            ->assertForbidden();

        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_ended_consignment_blocks_new_maintenance(): void
    {
        $consignment = $this->consign();
        $consignment->update([
            'status' => VehicleConsignment::STATUS_ENDED,
            'ended_at' => now(),
            'end_reason' => 'sold',
        ]);

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->maintenancePayload())
            ->assertForbidden();
    }

    public function test_consigning_garage_does_not_see_owner_history(): void
    {
        $this->consign();
        $ownerMaintenance = $this->ownerMaintenance();

        $response = $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk();

        $vehicle = $response->viewData('vehicle');

        $this->assertFalse($response->viewData('seesFullHistory'));
        $this->assertSame(0, $vehicle->maintenances_count);
        $this->assertFalse($vehicle->maintenances->contains('id', $ownerMaintenance->id));
        $this->assertFalse($vehicle->provenanceStripMaintenances->contains('id', $ownerMaintenance->id));
    }

    public function test_approved_history_access_reveals_owner_history(): void
    {
        $this->consign(VehicleConsignment::HISTORY_APPROVED);
        $ownerMaintenance = $this->ownerMaintenance();

        $response = $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk();

        $this->assertTrue($response->viewData('seesFullHistory'));
        $this->assertTrue($response->viewData('vehicle')->maintenances->contains('id', $ownerMaintenance->id));
    }

    public function test_consigning_garage_cannot_pull_history_pdf(): void
    {
        $this->consign();

        $this->actingAs($this->garage)
            ->postJson('/api/v1/vehicles/'.$this->vehicle->id.'/export-pdf')
            ->assertForbidden();
    }

    public function test_consigned_vehicle_shows_up_in_stock(): void
    {
        $this->consign();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee($this->vehicle->license_plate)
            ->assertSee('Consignação');
    }

    public function test_ending_consignment_keeps_registered_maintenances(): void
    {
        $this->consign();

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
        ]);

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.end', $this->vehicle), ['end_reason' => 'sold'])
            ->assertRedirect(route('garage.vehicles.index'));

        $this->assertDatabaseHas('vehicle_consignments', [
            'vehicle_id' => $this->vehicle->id,
            'status' => VehicleConsignment::STATUS_ENDED,
            'end_reason' => 'sold',
        ]);
        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertForbidden();
    }

    public function test_consignment_links_owner_account_when_vehicle_already_belongs_to_a_user(): void
    {
        $consignment = $this->consign();

        $this->assertSame($this->owner->id, $consignment->owner_user_id);
    }

    private function consign(string $historyStatus = VehicleConsignment::HISTORY_NONE): VehicleConsignment
    {
        $this->garage->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        $consignment = app(\App\Services\Vehicle\VehicleConsignmentService::class)->start(
            $this->garage,
            $this->vehicle,
            [
                'owner_name' => 'Maria Souza',
                'owner_email' => null,
                'owner_phone' => null,
                'owner_document' => '12345678901',
            ],
        );

        return tap($consignment)->update(['history_access_status' => $historyStatus]);
    }

    private function ownerMaintenance(): Maintenance
    {
        return Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(): array
    {
        return [
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Revisão pré-venda',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 60_000,
            'service_category' => 'mechanical',
        ];
    }
}
