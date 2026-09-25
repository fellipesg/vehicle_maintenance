<?php

namespace Tests\Feature\Web\Garage;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Notifications\ConsignmentHistoryAccessRequestedNotification;
use App\Notifications\ConsignmentMaintenanceRegisteredNotification;
use App\Notifications\VehicleConsignmentStartedNotification;
use App\Services\Vehicle\VehicleConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConsignmentOwnerResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $garage;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
        $this->owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);
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

    public function test_registered_owner_is_notified_when_consignment_starts(): void
    {
        Notification::fake();

        $this->consign();

        Notification::assertSentTo($this->owner, VehicleConsignmentStartedNotification::class);
    }

    public function test_owner_without_account_is_notified_by_email(): void
    {
        Notification::fake();

        $vehicle = Vehicle::factory()->create();
        app(VehicleConsignmentService::class)->start($this->garage, $vehicle, [
            'owner_name' => 'Sem Conta',
            'owner_email' => 'sem-conta@example.com',
        ]);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            VehicleConsignmentStartedNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'sem-conta@example.com',
        );
    }

    public function test_owner_is_notified_for_every_maintenance_the_garage_registers(): void
    {
        $this->consign();
        Notification::fake();

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), [
                'vehicle_id' => $this->vehicle->id,
                'maintenance_type' => 'Troca de óleo',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 60_000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect(route('garage.maintenances.index'));

        Notification::assertSentTo($this->owner, ConsignmentMaintenanceRegisteredNotification::class);
    }

    public function test_owner_page_opens_with_the_token_and_404s_without_it(): void
    {
        $consignment = $this->consign();

        $this->get(route('consignments.owner.show', $consignment->owner_action_token))
            ->assertOk()
            ->assertSee($this->garage->name)
            ->assertSee('Liberar histórico para a garagem');

        $this->get(route('consignments.owner.show', 'token-invalido'))->assertNotFound();
    }

    public function test_owner_page_does_not_expose_maintenances_from_other_tenants(): void
    {
        $consignment = $this->consign();

        $ownerMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
            'maintenance_type' => 'Revisao do proprietario',
        ]);

        $this->get(route('consignments.owner.show', $consignment->owner_action_token))
            ->assertOk()
            ->assertDontSee($ownerMaintenance->maintenance_type);
    }

    public function test_owner_releases_the_history_with_one_click(): void
    {
        $consignment = $this->consign();

        $ownerMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);

        $this->post(route('consignments.owner.approve', $consignment->owner_action_token))
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_consignments', [
            'id' => $consignment->id,
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'history_approved_via' => 'owner',
        ]);

        $response = $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk();

        $this->assertTrue($response->viewData('vehicle')->maintenances->contains('id', $ownerMaintenance->id));
    }

    public function test_dispute_blocks_new_maintenances_but_keeps_the_vehicle_visible(): void
    {
        $consignment = $this->consign();

        $this->post(route('consignments.owner.dispute', $consignment->owner_action_token), [
            'note' => 'Não autorizei esta garagem.',
        ])->assertRedirect();

        $this->assertDatabaseHas('vehicle_consignments', [
            'id' => $consignment->id,
            'owner_dispute_note' => 'Não autorizei esta garagem.',
        ]);

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), [
                'vehicle_id' => $this->vehicle->id,
                'maintenance_type' => 'Troca de óleo',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 60_000,
                'service_category' => 'mechanical',
            ])
            ->assertForbidden();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->vehicle))
            ->assertOk()
            ->assertSee('contestou esta consignação');
    }

    public function test_dispute_also_cuts_history_access_that_had_been_granted(): void
    {
        $consignment = $this->consign();
        app(VehicleConsignmentService::class)->approveHistoryAccess($consignment, 'owner');

        $this->post(route('consignments.owner.dispute', $consignment->owner_action_token))->assertRedirect();

        $response = $this->actingAs($this->garage)->get(route('garage.vehicles.show', $this->vehicle));

        $this->assertFalse($response->viewData('seesFullHistory'));
    }

    public function test_garage_can_ask_the_owner_to_release_the_history(): void
    {
        $consignment = $this->consign();
        Notification::fake();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.request-history', $this->vehicle))
            ->assertRedirect();

        Notification::assertSentTo($this->owner, ConsignmentHistoryAccessRequestedNotification::class);

        $this->assertSame(VehicleConsignment::HISTORY_PENDING, $consignment->fresh()->history_access_status);
    }

    private function consign(): VehicleConsignment
    {
        $this->garage->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        return app(VehicleConsignmentService::class)->start($this->garage, $this->vehicle, [
            'owner_name' => $this->owner->name,
        ]);
    }
}
