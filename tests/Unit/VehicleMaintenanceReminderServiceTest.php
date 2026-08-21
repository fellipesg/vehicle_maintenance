<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMaintenanceReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleMaintenanceReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VehicleMaintenanceReminderService::class);
    }

    public function test_calculates_next_due_from_last_maintenance(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 97_400,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2024-01-10',
        ]);

        $summary = $this->service->summarize($vehicle->fresh(['maintenances']));

        $this->assertSame(80_000, $summary['anchor_kilometers']);
        $this->assertSame(100_000, $summary['next_due_kilometers']);
        $this->assertSame(2_600, $summary['kilometers_remaining']);
        $this->assertFalse($summary['is_overdue']);
        $this->assertSame(87.0, $summary['progress_percent']);
    }

    public function test_progress_uses_interval_start_when_at_last_maintenance(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => null,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 110_000,
            'maintenance_date' => '2026-06-13',
        ]);

        $summary = $this->service->summarize($vehicle->fresh(['maintenances']));

        $this->assertSame(120_000, $summary['next_due_kilometers']);
        $this->assertSame(50.0, $summary['progress_percent']);
    }

    public function test_should_notify_when_within_threshold(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 98_500,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2024-01-10',
        ]);

        $this->assertTrue($this->service->shouldNotify($vehicle->fresh(['maintenances'])));
    }
}
