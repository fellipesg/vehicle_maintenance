<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test listing all maintenances
     */
    public function test_can_list_maintenances(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        
        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/maintenances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'maintenance_type',
                            'maintenance_date',
                            'kilometers',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test filtering maintenances by vehicle
     */
    public function test_can_filter_maintenances_by_vehicle(): void
    {
        $vehicle1 = Vehicle::factory()->create();
        $vehicle2 = Vehicle::factory()->create();
        $user = User::factory()->create();
        
        Maintenance::factory()->count(2)->create([
            'vehicle_id' => $vehicle1->id,
            'user_id' => $user->id,
        ]);
        
        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle2->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/maintenances?vehicle_id={$vehicle1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    /**
     * Test creating a maintenance
     */
    public function test_can_create_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();

        $maintenanceData = [
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'maintenance_type' => 'Revisão 10.000 km',
            'description' => 'Troca de óleo e filtros',
            'workshop_name' => 'Oficina Teste',
            'maintenance_date' => '2024-01-15',
            'kilometers' => 10000,
            'service_category' => 'mechanical',
            'is_manufacturer_required' => true,
            'items' => [
                [
                    'name' => 'Óleo Motor',
                    'description' => 'Óleo sintético 5W30',
                    'quantity' => 1,
                    'unit_price' => 45.90,
                    'total_price' => 45.90,
                ],
                [
                    'name' => 'Filtro de Óleo',
                    'quantity' => 1,
                    'unit_price' => 25.00,
                    'total_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/maintenances', $maintenanceData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'maintenance_type' => 'Revisão 10.000 km',
                    'kilometers' => 10000,
                ],
            ]);

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão 10.000 km',
        ]);

        $this->assertDatabaseHas('maintenance_items', [
            'name' => 'Óleo Motor',
        ]);
    }

    /**
     * Test creating maintenance with invalid data
     */
    public function test_cannot_create_maintenance_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/maintenances', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'errors',
            ]);
    }

    /**
     * Test creating maintenance with invalid vehicle
     */
    public function test_cannot_create_maintenance_with_invalid_vehicle(): void
    {
        $user = User::factory()->create();

        $maintenanceData = [
            'vehicle_id' => 999,
            'user_id' => $user->id,
            'maintenance_type' => 'Test',
            'maintenance_date' => '2024-01-15',
            'kilometers' => 10000,
            'service_category' => 'mechanical',
        ];

        $response = $this->postJson('/api/v1/maintenances', $maintenanceData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    /**
     * Test showing a specific maintenance
     */
    public function test_can_show_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $maintenance->id,
                ],
            ]);
    }

    /**
     * Test updating a maintenance
     */
    public function test_can_update_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $updateData = [
            'description' => 'Updated description',
            'workshop_name' => 'Updated Workshop',
        ];

        $response = $this->putJson("/api/v1/maintenances/{$maintenance->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'description' => 'Updated description',
                    'workshop_name' => 'Updated Workshop',
                ],
            ]);
    }

    /**
     * Test deleting a maintenance
     */
    public function test_can_delete_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('maintenances', [
            'id' => $maintenance->id,
        ]);
    }

    /**
     * Test filtering maintenances by service category
     */
    public function test_can_filter_maintenances_by_category(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'service_category' => 'mechanical',
        ]);
        
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'service_category' => 'electrical',
        ]);

        $response = $this->getJson('/api/v1/maintenances?service_category=mechanical');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.service_category', 'mechanical');
    }
}
