<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $garage;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->asAdmin()->create();
        $this->garage = User::factory()->asGarage()->create();
        $this->vehicle = Vehicle::factory()->create();
    }

    public function test_non_admin_cannot_reach_the_queue(): void
    {
        $this->actingAs($this->garage)
            ->get(route('admin.consignments.index'))
            ->assertForbidden();
    }

    public function test_queue_highlights_pending_and_disputed(): void
    {
        $pending = $this->consignment(['history_access_status' => VehicleConsignment::HISTORY_PENDING]);
        $quiet = $this->consignment();

        $this->actingAs($this->admin)
            ->get(route('admin.consignments.index'))
            ->assertOk()
            ->assertSee($pending->owner_name)
            ->assertDontSee($quiet->owner_name);
    }

    public function test_staff_approval_releases_the_history(): void
    {
        $consignment = $this->consignment(['history_access_status' => VehicleConsignment::HISTORY_PENDING]);
        $this->attachGarage();

        $ownerMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => User::factory()->asUser()->create()->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.consignments.approve', $consignment))
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_consignments', [
            'id' => $consignment->id,
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'history_approved_via' => 'staff',
            'reviewed_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->garage)->get(route('garage.vehicles.show', $this->vehicle));

        $this->assertTrue($response->viewData('vehicle')->maintenances->contains('id', $ownerMaintenance->id));
    }

    public function test_staff_can_reject_with_a_note(): void
    {
        $consignment = $this->consignment(['history_access_status' => VehicleConsignment::HISTORY_PENDING]);

        $this->actingAs($this->admin)
            ->post(route('admin.consignments.reject', $consignment), ['review_notes' => 'Procuração ilegível.'])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_consignments', [
            'id' => $consignment->id,
            'history_access_status' => VehicleConsignment::HISTORY_REJECTED,
            'review_notes' => 'Procuração ilegível.',
        ]);
    }

    public function test_revoking_removes_the_garage_access(): void
    {
        $consignment = $this->consignment();
        $this->attachGarage();

        $this->actingAs($this->admin)
            ->post(route('admin.consignments.revoke', $consignment))
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_consignments', [
            'id' => $consignment->id,
            'status' => VehicleConsignment::STATUS_ENDED,
            'end_reason' => 'revoked',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertForbidden();
    }

    public function test_archiving_a_dispute_lets_the_garage_work_again(): void
    {
        $consignment = $this->consignment(['owner_disputed_at' => now()]);
        $this->attachGarage();

        $this->actingAs($this->admin)
            ->post(route('admin.consignments.clear-dispute', $consignment))
            ->assertRedirect();

        $this->assertNull($consignment->fresh()->owner_disputed_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function consignment(array $attributes = []): VehicleConsignment
    {
        return VehicleConsignment::factory()->create(array_merge([
            'vehicle_id' => $this->vehicle->id,
            'garage_user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
        ], $attributes));
    }

    private function attachGarage(): void
    {
        $this->garage->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);
    }
}
