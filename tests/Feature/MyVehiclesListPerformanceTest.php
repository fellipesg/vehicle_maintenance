<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\VehiclePlate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyVehiclesListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_vehicles_default_payload_excludes_plates_and_provenance_strip(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        VehiclePlate::factory()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->create(['vehicle_id' => $vehicle->id]);

        $response = $this->getJson('/api/v1/my-vehicles');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $vehicle->id)
            ->assertJsonMissingPath('data.0.plate_history')
            ->assertJsonMissingPath('data.0.provenance_strip');
    }

    public function test_my_vehicles_include_plates_and_provenance_strip(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        VehiclePlate::factory()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->create(['vehicle_id' => $vehicle->id]);

        $response = $this->getJson('/api/v1/my-vehicles?include=plates,provenance_strip');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'plate_history',
                        'provenance_strip',
                    ],
                ],
            ]);
    }

    public function test_my_vehicles_list_query_count_is_constant_with_many_vehicles(): void
    {
        $user = $this->actingAsApiUser();

        foreach (range(1, 10) as $index) {
            $vehicle = Vehicle::factory()->create(['license_plate' => 'LST'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)]);
            $this->attachVehicleToUser($user, $vehicle);
            VehiclePlate::factory()->create(['vehicle_id' => $vehicle->id]);
            Maintenance::factory()->count(2)->create(['vehicle_id' => $vehicle->id]);
        }

        $queriesForTen = $this->countQueries(fn () => $this->getJson('/api/v1/my-vehicles?per_page=50')->assertOk());

        $extraVehicle = Vehicle::factory()->create(['license_plate' => 'LST0011']);
        $this->attachVehicleToUser($user, $extraVehicle);
        VehiclePlate::factory()->create(['vehicle_id' => $extraVehicle->id]);
        Maintenance::factory()->count(2)->create(['vehicle_id' => $extraVehicle->id]);

        $queriesForEleven = $this->countQueries(fn () => $this->getJson('/api/v1/my-vehicles?per_page=50')->assertOk());

        $this->assertSame($queriesForTen, $queriesForEleven);
        $this->assertLessThanOrEqual(12, $queriesForTen);
    }
}
