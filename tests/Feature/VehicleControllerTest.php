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

    /**
     * Test listing all vehicles
     */
    public function test_can_list_vehicles(): void
    {
        Vehicle::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'license_plate',
                            'renavam',
                            'brand',
                            'model',
                            'year',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test creating a vehicle
     */
    public function test_can_create_vehicle(): void
    {
        $vehicleData = [
            'license_plate' => 'ABC1234',
            'renavam' => '12345678901',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'color' => 'Branco',
        ];

        $response = $this->postJson('/api/v1/vehicles', $vehicleData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'license_plate' => 'ABC1234',
                    'renavam' => '12345678901',
                    'brand' => 'Toyota',
                    'model' => 'Corolla',
                ],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'ABC1234',
            'renavam' => '12345678901',
        ]);
    }

    /**
     * Test creating vehicle with invalid data
     */
    public function test_cannot_create_vehicle_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/vehicles', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'errors',
            ]);
    }

    /**
     * Test creating vehicle with duplicate license plate
     */
    public function test_cannot_create_vehicle_with_duplicate_license_plate(): void
    {
        Vehicle::factory()->create(['license_plate' => 'ABC1234']);

        $vehicleData = [
            'license_plate' => 'ABC1234',
            'renavam' => '98765432109',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
        ];

        $response = $this->postJson('/api/v1/vehicles', $vehicleData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_plate']);
    }

    /**
     * Test showing a specific vehicle
     */
    public function test_can_show_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'license_plate' => $vehicle->license_plate,
                ],
            ]);
    }

    /**
     * Test showing non-existent vehicle
     */
    public function test_cannot_show_nonexistent_vehicle(): void
    {
        $response = $this->getJson('/api/v1/vehicles/999');

        $response->assertStatus(404);
    }

    /**
     * Test updating a vehicle
     */
    public function test_can_update_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $updateData = [
            'brand' => 'Updated Brand',
            'model' => 'Updated Model',
        ];

        $response = $this->putJson("/api/v1/vehicles/{$vehicle->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'brand' => 'Updated Brand',
                    'model' => 'Updated Model',
                ],
            ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'brand' => 'Updated Brand',
            'model' => 'Updated Model',
        ]);
    }

    /**
     * Test deleting a vehicle
     */
    public function test_can_delete_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->deleteJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
        ]);
    }

    /**
     * Test searching vehicle by license plate
     */
    public function test_can_search_vehicle_by_license_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'XYZ9876']);

        $response = $this->getJson('/api/v1/vehicles/search/XYZ9876');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'license_plate' => 'XYZ9876',
                ],
            ]);
    }

    /**
     * Test searching vehicle by RENAVAM
     */
    public function test_can_search_vehicle_by_renavam(): void
    {
        $vehicle = Vehicle::factory()->create(['renavam' => '11122233344']);

        $response = $this->getJson('/api/v1/vehicles/search/11122233344');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'renavam' => '11122233344',
                ],
            ]);
    }

    /**
     * Test searching non-existent vehicle
     */
    public function test_search_returns_404_for_nonexistent_vehicle(): void
    {
        $response = $this->getJson('/api/v1/vehicles/search/INVALID');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test getting vehicle maintenances
     */
    public function test_can_get_vehicle_maintenances(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        
        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');
    }
}
