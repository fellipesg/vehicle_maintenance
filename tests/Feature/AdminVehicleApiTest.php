<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVehicleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
    }

    public function test_non_admin_cannot_list_admin_vehicles(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user, ['vehicles:read']);

        $this->getJson('/api/v1/admin/vehicles')->assertForbidden();
    }

    public function test_admin_receives_vehicles_from_multiple_owners(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        Sanctum::actingAs($admin, ['vehicles:read']);

        $ownerA = User::factory()->asUser()->create();
        $ownerB = User::factory()->asUser()->create();

        $vehicleA = Vehicle::factory()->create(['license_plate' => 'ADM1A11']);
        $vehicleB = Vehicle::factory()->create(['license_plate' => 'ADM2B22']);

        $ownerA->vehicles()->attach($vehicleA->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $ownerA->tenant_id,
        ]);
        $ownerB->vehicles()->attach($vehicleB->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $ownerB->tenant_id,
        ]);

        $response = $this->getJson('/api/v1/admin/vehicles');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $plates = collect($response->json('data'))->pluck('license_plate')->all();
        $this->assertContains('ADM1A11', $plates);
        $this->assertContains('ADM2B22', $plates);
    }

    public function test_admin_can_view_vehicle_they_do_not_own(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        Sanctum::actingAs($admin, ['vehicles:read']);

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }
}
