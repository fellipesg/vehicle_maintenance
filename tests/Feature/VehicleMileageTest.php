<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMileageTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_registration_requires_kilometers_and_terms(): void
    {
        $this->actingAsApiUser();

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
        $user = $this->actingAsApiUser();

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
        $user = $this->actingAsApiUser();

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
        $user = $this->actingAsApiUser();

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
        $user = $this->actingAsApiUser();

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

    public function test_timeline_sorts_events_by_kilometers_then_date(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 100_000,
            'odometer_at_registration' => 50_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 94_000,
            'maintenance_date' => '2025-11-22',
            'maintenance_type' => 'Demo controle A',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 94_000,
            'maintenance_date' => '2025-11-15',
            'maintenance_type' => 'Demo controle B',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 100_000,
            'maintenance_date' => '2026-08-15',
            'maintenance_type' => 'Última revisão',
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.events.0.kilometers', 50_000)
            ->assertJsonPath('data.events.1.kilometers', 94_000)
            ->assertJsonPath('data.events.1.date', '2025-11-15')
            ->assertJsonPath('data.events.2.kilometers', 94_000)
            ->assertJsonPath('data.events.2.date', '2025-11-22')
            ->assertJsonPath('data.events.3.kilometers', 100_000)
            ->assertJsonPath('data.events.3.is_current', true);
    }

    public function test_timeline_track_progress_reaches_last_maintenance_even_when_earlier_nodes_exist(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => 50_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 96_000,
            'maintenance_date' => '2026-02-08',
            'maintenance_type' => 'Demo Brothers — pastilhas (vigente)',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 105_103,
            'maintenance_date' => '2026-03-12',
            'maintenance_type' => 'Revisão API XML 33893',
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 110_000,
            'maintenance_date' => '2026-08-15',
            'maintenance_type' => 'Troca Válvula Termostática',
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk();

        $events = $response->json('data.events');
        $eventCount = count($events);
        $lastMaintenanceIndex = collect($events)
            ->keys()
            ->last(fn (int $index) => ($events[$index]['type'] ?? '') !== 'upcoming');

        $expectedProgress = round($lastMaintenanceIndex / ($eventCount - 1) * 100, 1);

        $this->assertEquals(110_000, $events[$lastMaintenanceIndex]['kilometers']);
        $this->assertEquals($expectedProgress, (float) $response->json('data.summary.track_progress_percent'));
    }

    public function test_timeline_track_progress_interpolates_toward_upcoming_when_odometer_is_past_last_maintenance(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 115_000,
            'odometer_at_registration' => 50_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 110_000,
            'maintenance_date' => '2026-08-15',
            'maintenance_type' => 'Troca Válvula Termostática',
        ]);

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk();

        $events = $response->json('data.events');
        $eventCount = count($events);
        $lastMaintenanceIndex = collect($events)
            ->keys()
            ->last(fn (int $index) => ($events[$index]['type'] ?? '') !== 'upcoming');
        $upcomingIndex = collect($events)->search(fn (array $event) => ($event['type'] ?? '') === 'upcoming');
        $lastMaintenanceKm = (int) $events[$lastMaintenanceIndex]['kilometers'];
        $upcomingKm = (int) $events[$upcomingIndex]['kilometers'];
        $currentKm = 115_000;
        $kmFraction = ($currentKm - $lastMaintenanceKm) / ($upcomingKm - $lastMaintenanceKm);

        $this->assertNotFalse($upcomingIndex);
        $this->assertGreaterThan($lastMaintenanceIndex, $upcomingIndex);

        $expectedProgress = round(
            ($lastMaintenanceIndex + $kmFraction * ($upcomingIndex - $lastMaintenanceIndex)) / ($eventCount - 1) * 100,
            1,
        );

        $this->assertEquals($expectedProgress, (float) $response->json('data.summary.track_progress_percent'));
    }

    public function test_timeline_track_progress_reaches_110k_event_with_demo_dataset(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => 80_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        foreach ([
            [80_000, '2024-01-10', 'OS 80k'],
            [82_000, '2024-03-10', 'OS 82k'],
            [94_000, '2025-06-14', 'OS 94k'],
            [95_000, '2025-11-15', 'Revisão B'],
            [95_500, '2025-11-22', 'OS 95500'],
            [96_000, '2026-02-08', 'OS 96k'],
            [105_103, '2026-03-12', 'Revisão API'],
            [110_000, '2026-06-13', 'Troca Válvula Termostática'],
        ] as [$kilometers, $date, $type]) {
            Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'kilometers' => $kilometers,
                'maintenance_date' => $date,
                'maintenance_type' => $type,
            ]);
        }

        $response = $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk();

        $events = $response->json('data.events');
        $index95k = collect($events)->search(fn (array $event) => ($event['kilometers'] ?? null) === 95_000);
        $index110k = collect($events)->search(fn (array $event) => ($event['kilometers'] ?? null) === 110_000);

        $this->assertNotFalse($index95k);
        $this->assertNotFalse($index110k);
        $this->assertGreaterThan($index95k, $index110k);
        $this->assertTrue($events[$index110k]['is_current']);
        $this->assertSame(0.0, (float) $response->json('data.summary.progress_percent'));
        $this->assertEquals(91.7, (float) $response->json('data.summary.odometer_progress_percent'));
        $this->assertSame(120_000, $response->json('data.summary.next_due_kilometers'));
        $this->assertSame($index110k, $response->json('data.summary.track_current_index'));

        $eventCount = count($events);
        $expectedTrackProgress = round($index110k / ($eventCount - 1) * 100, 1);
        $this->assertEquals($expectedTrackProgress, (float) $response->json('data.summary.track_progress_percent'));
        $this->assertGreaterThan(50.0, (float) $response->json('data.summary.track_progress_percent'));
    }

    public function test_vehicle_timeline_endpoint_uses_bounded_query_count(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => 80_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        foreach ([80_000, 95_000, 110_000] as $kilometers) {
            $maintenance = Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'kilometers' => $kilometers,
            ]);

            MaintenanceItem::factory()->create([
                'maintenance_id' => $maintenance->id,
            ]);
        }

        $queryCount = $this->countQueries(fn () => $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")->assertOk());

        $this->assertLessThanOrEqual(20, $queryCount);
    }

    public function test_vehicle_maintenances_endpoint_uses_bounded_query_count(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 110_000,
            'odometer_at_registration' => 80_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        foreach ([80_000, 95_000, 110_000] as $kilometers) {
            $maintenance = Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'kilometers' => $kilometers,
            ]);

            MaintenanceItem::factory()->create([
                'maintenance_id' => $maintenance->id,
            ]);
        }

        $queryCount = $this->countQueries(
            fn () => $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances")->assertOk(),
        );

        $this->assertLessThanOrEqual(20, $queryCount);
    }

    public function test_timeline_items_include_warranty_fields(): void
    {
        $user = $this->actingAsApiUser();

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 60_000,
            'odometer_at_registration' => 60_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $workshop = \App\Models\Workshop::factory()->create([
            'logo_path' => \App\Support\AppStorage::WORKSHOP_LOGOS_PREFIX.'timeline_test.jpg',
        ]);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 62_000,
            'maintenance_date' => now()->subDays(10),
        ]);

        MaintenanceWarranty::factory()->orderScope()->create([
            'maintenance_id' => $maintenance->id,
            'name' => 'Garantia geral OS',
            'duration_days' => 180,
        ]);

        $item = MaintenanceItem::factory()->create([
            'maintenance_id' => $maintenance->id,
            'name' => 'Filtro de Óleo',
        ]);
        MaintenanceWarranty::factory()->itemScope($item)->create([
            'maintenance_id' => $maintenance->id,
            'name' => 'Garantia peça',
            'duration_days' => 90,
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/timeline")
            ->assertOk()
            ->assertJsonPath('data.events.1.items.0.name', 'Filtro de Óleo')
            ->assertJsonPath('data.events.1.items.0.has_warranty', true)
            ->assertJsonPath('data.events.1.items.0.warranty_name', 'Garantia peça')
            ->assertJsonPath('data.events.1.items.0.is_under_warranty', true)
            ->assertJsonPath('data.events.1.workshop_logo_url', $workshop->logoUrl())
            ->assertJsonPath('data.events.1.general_warranty.name', 'Garantia geral OS')
            ->assertJsonPath('data.events.1.general_warranty.is_vigente', true)
            ->assertJsonStructure([
                'data' => [
                    'events' => [
                        1 => [
                            'workshop_logo_url',
                            'general_warranty' => [
                                'name',
                                'ends_at',
                                'is_vigente',
                                'label',
                            ],
                            'items' => [
                                0 => [
                                    'warranty_starts_at',
                                    'warranty_ends_at',
                                    'warranty_period_label',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
