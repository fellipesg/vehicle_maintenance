<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMileageStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleMileageStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VehicleMileageStatsService::class);
    }

    public function test_calculates_approximate_annual_kilometers_from_maintenances(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 97_400,
            'odometer_at_registration' => 70_000,
            'created_at' => now()->subYears(3),
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => now()->subYear()->toDateString(),
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 97_400,
            'maintenance_date' => now()->toDateString(),
        ]);

        $stats = $this->service->approximateAnnualKilometers($vehicle->fresh(['maintenances']));

        $this->assertNotNull($stats);
        $this->assertTrue($stats['is_approximate']);
        $this->assertSame(27_400, $stats['kilometers_driven']);
        $this->assertGreaterThan(8_000, $stats['approximate_annual_kilometers']);
    }

    public function test_historical_maintenance_enters_annual_average_without_lowering_odometer(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => 110_000,
            'created_at' => now(),
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $this->assertNull($this->service->approximateAnnualKilometers($vehicle->fresh(['maintenances'])));

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 96_000,
            'maintenance_date' => now()->subYear()->toDateString(),
        ]);

        $stats = $this->service->approximateAnnualKilometers($vehicle->fresh(['maintenances']));

        $this->assertSame(110_000, $vehicle->fresh()->current_kilometers);
        $this->assertNotNull($stats);
        $this->assertSame(14_000, $stats['kilometers_driven']);
        $this->assertSame(now()->subYear()->toDateString(), $stats['period_start_date']);
        $this->assertSame(now()->toDateString(), $stats['period_end_date']);
        $this->assertGreaterThan(10_000, $stats['approximate_annual_kilometers']);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => now()->subYears(2)->toDateString(),
        ]);

        $enriched = $this->service->approximateAnnualKilometers($vehicle->fresh(['maintenances']));

        $this->assertSame(110_000, $vehicle->fresh()->current_kilometers);
        $this->assertNotNull($enriched);
        $this->assertSame(30_000, $enriched['kilometers_driven']);
        $this->assertSame(now()->subYears(2)->toDateString(), $enriched['period_start_date']);
        $this->assertSame(now()->toDateString(), $enriched['period_end_date']);
    }

    public function test_returns_null_when_insufficient_data(): void
    {
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50_000,
            'odometer_at_registration' => null,
        ]);

        $this->assertNull($this->service->approximateAnnualKilometers($vehicle->fresh(['maintenances'])));
    }
}
