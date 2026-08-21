<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMileageTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_registration_requires_kilometers_and_terms(): void
    {
        Sanctum::actingAs(User::factory()->asUser()->create());

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'KM12345',
            'renavam' => '12345678901',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_kilometers', 'terms_accepted']);
    }

    public function test_maintenance_updates_vehicle_current_kilometers(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50000,
            'odometer_at_registration' => 50000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => '2024-06-01',
            'kilometers' => 52000,
            'service_category' => 'mechanical',
        ])->assertCreated();

        $this->assertSame(52000, $vehicle->fresh()->current_kilometers);
    }

    public function test_maintenance_cannot_use_lower_kilometers_than_current(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 80000,
            'odometer_at_registration' => 80000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => '2024-06-01',
            'kilometers' => 79000,
            'service_category' => 'mechanical',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kilometers']);
    }

    public function test_vehicle_timeline_endpoint_returns_events(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 60000,
            'odometer_at_registration' => 60000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 62000,
            'maintenance_date' => '2024-03-10',
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.events')
            ->assertJsonPath('data.summary.next_due_kilometers', 72_000)
            ->assertJsonPath('data.events.2.type', 'upcoming');
    }

    public function test_timeline_registration_uses_first_maintenance_when_odometer_missing(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => null,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-06-14',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 110_000,
            'maintenance_date' => '2026-06-13',
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.events.0.type', 'maintenance')
            ->assertJsonPath('data.events.0.kilometers', 80_000);
    }
}
