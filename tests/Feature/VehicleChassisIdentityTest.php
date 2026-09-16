<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\VehiclePlate;
use App\Services\Vehicle\VehiclePlateHistoryService;
use App\Support\Vehicle\VehicleLookupResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleChassisIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_chassis_is_normalized_to_uppercase_alphanumeric(): void
    {
        $this->assertSame('9BWZZZ377VT004251', Vehicle::normalizeChassis('9bwzzz-377 vt004251'));
    }

    public function test_duplicate_chassis_returns_friendly_api_message(): void
    {
        $this->actingAsApiUser();
        Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004251']);

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'NEW1A23',
            'renavam' => '99887766554',
            'chassis' => '9BWZZZ377VT004251',
            'brand' => 'Fiat',
            'model' => 'Uno',
            'year' => 2020,
            'current_kilometers' => 1000,
            'terms_accepted' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['chassis'])
            ->assertJsonPath('errors.chassis.0', 'Já existe um veículo com este chassi. Você pode vinculá-lo em Vincular veículo.');
    }

    public function test_search_by_chassis_returns_matched_by(): void
    {
        $vehicle = Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004299']);

        $this->getJson('/api/v1/vehicles/search/9BWZZZ377VT004299')
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id)
            ->assertJsonPath('data.matched_by', VehicleLookupResult::MATCH_CHASSIS);
    }

    public function test_search_by_previous_plate_returns_banner_fields(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'NEW2B34']);
        VehiclePlate::factory()->create([
            'vehicle_id' => $vehicle->id,
            'plate' => 'OLD3C45',
            'started_at' => now()->subYears(2),
            'ended_at' => now()->subMonths(6),
            'source' => 'manual',
        ]);

        $this->getJson('/api/v1/vehicles/search/OLD3C45')
            ->assertOk()
            ->assertJsonPath('data.matched_by', VehicleLookupResult::MATCH_PREVIOUS_PLATE)
            ->assertJsonPath('data.license_plate', 'NEW2B34')
            ->assertJsonStructure(['data' => ['previous_plate_ended_at']]);
    }

    public function test_api_plate_change_creates_history_and_closes_current(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'PLT1A11']);
        $this->attachVehicleToUser($user, $vehicle);
        app(VehiclePlateHistoryService::class)->recordInitialPlate($vehicle, 'manual', $user);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'license_plate' => 'PLT2B22',
            'plate_changed_at' => '2024-06-01',
        ])->assertOk();

        $vehicle->refresh();
        $this->assertSame('PLT2B22', $vehicle->license_plate);
        $this->assertDatabaseHas('vehicle_plates', [
            'vehicle_id' => $vehicle->id,
            'plate' => 'PLT1A11',
        ]);
        $this->assertDatabaseHas('vehicle_plates', [
            'vehicle_id' => $vehicle->id,
            'plate' => 'PLT2B22',
            'ended_at' => null,
        ]);
    }

    public function test_plates_endpoint_lists_history(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        VehiclePlate::factory()->count(2)->create(['vehicle_id' => $vehicle->id]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/plates")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_api_store_creates_initial_plate_history(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'BFLL123',
            'renavam' => '11223344556',
            'chassis' => '9BWZZZ377VT004288',
            'brand' => 'VW',
            'model' => 'Gol',
            'year' => 2021,
            'current_kilometers' => 20000,
            'terms_accepted' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('vehicle_plates', [
            'plate' => 'BFLL123',
            'source' => 'api',
            'ended_at' => null,
        ]);
    }

    public function test_public_search_page_shows_previous_plate_banner(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'CUR4D56']);
        VehiclePlate::factory()->create([
            'vehicle_id' => $vehicle->id,
            'plate' => 'OLD5E67',
            'ended_at' => now()->subMonth(),
            'source' => 'manual',
        ]);

        $this->get(route('vehicle.search', ['identifier' => 'OLD5E67']))
            ->assertOk()
            ->assertSee('OLD5E67', false)
            ->assertSee('CUR4D56', false);
    }
}
