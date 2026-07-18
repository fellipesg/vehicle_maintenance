<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_unauthenticated_cannot_list_vehicles(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
    }

    public function test_can_list_only_tenant_vehicles(): void
    {
        $user = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();

        $owned = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();

        $this->attachVehicleToUser($user, $owned);
        $this->attachVehicleToUser($otherUser, $other);

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $owned->id)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_can_create_vehicle(): void
    {
        $this->actingAsApiUser();

        $vehicleData = [
            'license_plate' => 'ABC1234',
            'renavam' => '12345678901',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'color' => 'Branco',
        ];

        $response = $this->postJson('/api/v1/vehicles', $vehicleData);

        $response->assertCreated()
            ->assertJsonPath('data.license_plate', 'ABC1234');

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC1234']);
    }

    public function test_cannot_create_vehicle_with_invalid_data(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['success', 'errors']);
    }

    public function test_cannot_create_vehicle_with_duplicate_license_plate(): void
    {
        $this->actingAsApiUser();
        Vehicle::factory()->create(['license_plate' => 'ABC1234']);

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1234',
            'renavam' => '98765432109',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['license_plate']);
    }

    public function test_can_show_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }

    public function test_cannot_show_vehicle_from_other_tenant(): void
    {
        $user = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($otherUser, $vehicle);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertForbidden();
    }

    public function test_cannot_show_nonexistent_vehicle(): void
    {
        $this->actingAsApiUser();

        $this->getJson('/api/v1/vehicles/999')->assertNotFound();
    }

    public function test_can_update_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'brand' => 'Updated Brand',
            'model' => 'Updated Model',
        ])->assertOk()
            ->assertJsonPath('data.brand', 'Updated Brand');
    }

    public function test_can_delete_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_vehicles', [
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_can_search_vehicle_by_license_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'XYZ9876']);

        $this->getJson('/api/v1/vehicles/search/XYZ9876')
            ->assertOk()
            ->assertJsonPath('data.license_plate', 'XYZ9876');
    }

    public function test_can_search_vehicle_by_renavam(): void
    {
        $vehicle = Vehicle::factory()->create(['renavam' => '11122233344']);

        $this->getJson('/api/v1/vehicles/search/11122233344')
            ->assertOk()
            ->assertJsonPath('data.renavam', '11122233344');
    }

    public function test_search_returns_404_for_nonexistent_vehicle(): void
    {
        $this->getJson('/api/v1/vehicles/search/INVALID')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_can_get_vehicle_maintenances(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
