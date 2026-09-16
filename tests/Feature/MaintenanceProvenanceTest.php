<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_api_store_receives_verification_seal(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50000,
            'odometer_at_registration' => 50000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        Workshop::factory()->create(['user_id' => $workshopUser->id]);
        $workshopUser->refresh();
        $this->actingAsApiUser($workshopUser);

        $response = $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 50000,
            'service_category' => 'mechanical',
        ]);

        $response->assertCreated();
        $maintenance = Maintenance::query()->latest('id')->first();
        $this->assertNotNull($maintenance);
        $this->assertSame('workshop', $maintenance->registered_by_type);
        $this->assertTrue($maintenance->isVerified());
        $this->assertMatchesRegularExpression('/^RVL-[A-Z2-9]{4}-[A-Z2-9]{2}$/', (string) $maintenance->verification_code);
    }

    public function test_owner_cannot_update_verified_maintenance(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = Workshop::factory()->create(['user_id' => $workshopUser->id]);

        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'registered_by_type' => 'workshop',
            'verified_at' => now(),
            'verified_workshop_id' => $workshop->id,
            'verification_code' => 'RVL-ABCD-EF',
        ]);

        $this->actingAs($owner);

        $this->assertFalse($owner->can('update', $maintenance));
    }

    public function test_verified_filter_on_maintenances_index(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 10_000,
        ]);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 10_000,
        ]);

        $this->getJson('/api/v1/maintenances?verified=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/maintenances?verified=0')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_vehicle_show_includes_provenance_strip_in_order(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_date' => '2024-01-01',
        ]);
        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_date' => '2024-06-01',
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}");
        $response->assertOk()
            ->assertJsonPath('data.verified_maintenances_count', 1)
            ->assertJsonCount(2, 'data.provenance_strip')
            ->assertJsonPath('data.provenance_strip.0.is_verified', false)
            ->assertJsonPath('data.provenance_strip.1.is_verified', true);
    }

    public function test_public_verification_page_returns_200_for_valid_code(): void
    {
        $vehicle = Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004251']);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'registered_by_type' => 'workshop',
            'verified_at' => now(),
            'verification_code' => 'RVL-TEST-12',
        ]);

        $this->get('/v/RVL-TEST-12')
            ->assertOk()
            ->assertSee('Registro verificado')
            ->assertSee('*********VT004251', false);
    }
}
