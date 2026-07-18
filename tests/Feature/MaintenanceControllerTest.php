<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_list_only_tenant_maintenances(): void
    {
        $user = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();

        Maintenance::factory()->count(2)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $this->getJson('/api/v1/maintenances')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_can_filter_maintenances_by_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle1 = Vehicle::factory()->create();
        $vehicle2 = Vehicle::factory()->create();

        Maintenance::factory()->count(2)->create([
            'vehicle_id' => $vehicle1->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle2->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->getJson("/api/v1/maintenances?vehicle_id={$vehicle1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_can_create_maintenance(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $response = $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
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
                    'quantity' => 1,
                    'unit_price' => 45.90,
                    'total_price' => 45.90,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.maintenance_type', 'Revisão 10.000 km');

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function test_cannot_create_maintenance_with_invalid_data(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/maintenances', [])
            ->assertUnprocessable();
    }

    public function test_cannot_create_maintenance_for_unowned_vehicle(): void
    {
        $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Test',
            'maintenance_date' => '2024-01-15',
            'service_category' => 'mechanical',
        ])->assertForbidden();
    }

    public function test_can_show_own_maintenance(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->getJson("/api/v1/maintenances/{$maintenance->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $maintenance->id);
    }

    public function test_cannot_show_other_tenant_maintenance(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $this->getJson("/api/v1/maintenances/{$maintenance->id}")
            ->assertForbidden();
    }

    public function test_can_update_own_maintenance(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'description' => 'Updated description',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Updated description');
    }

    public function test_can_delete_own_maintenance(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")
            ->assertOk();

        $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id]);
    }

    public function test_can_filter_maintenances_by_category(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'service_category' => 'mechanical',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'service_category' => 'electrical',
        ]);

        $this->getJson('/api/v1/maintenances?service_category=mechanical')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.service_category', 'mechanical');
    }
}
