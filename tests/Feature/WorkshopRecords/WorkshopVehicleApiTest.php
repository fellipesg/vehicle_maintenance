<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * API da oficina: consulta e cadastro de veículo pelo chassi, e OS em carro sem proprietário.
 */
class WorkshopVehicleApiTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    public function test_lookup_of_an_unknown_chassis(): void
    {
        $this->actingAsApiUser($this->workshopAccount());

        $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.self::OWNERLESS_CHASSIS)
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['found' => false, 'vehicle' => null]]);
    }

    public function test_lookup_of_an_ownerless_vehicle_returns_the_model(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->actingAsApiUser($this->workshopAccount());

        $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.strtolower(self::OWNERLESS_CHASSIS))
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.vehicle', ['id' => $vehicle->id, 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021, 'has_owner' => false]);
    }

    public function test_lookup_of_an_owned_vehicle_leaks_nothing(): void
    {
        $vehicle = $this->ownerlessVehicle(['brand' => 'Ferrari']);
        $this->ownerOf($vehicle);
        $this->actingAsApiUser($this->workshopAccount());

        $response = $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.self::OWNERLESS_CHASSIS)->assertOk();

        $this->assertSame(['id' => $vehicle->id, 'has_owner' => true], $response->json('data.vehicle'));
        $this->assertStringNotContainsString('Ferrari', $response->getContent());
    }

    public function test_lookup_requires_17_characters_and_a_workshop_account(): void
    {
        $this->actingAsApiUser($this->workshopAccount());
        $this->getJson('/api/v1/workshop/vehicles/lookup?chassis=ABC')->assertStatus(422);

        $this->actingAsApiUser();
        $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.self::OWNERLESS_CHASSIS)->assertForbidden();
    }

    public function test_lookup_is_rate_limited(): void
    {
        $this->actingAsApiUser($this->workshopAccount());

        foreach (range(1, 20) as $ignored) {
            $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.self::OWNERLESS_CHASSIS)->assertOk();
        }

        $this->getJson('/api/v1/workshop/vehicles/lookup?chassis='.self::OWNERLESS_CHASSIS)->assertStatus(429);
    }

    public function test_workshop_creates_a_vehicle_without_plate_or_renavam(): void
    {
        $this->actingAsApiUser($this->workshopAccount());

        $this->postJson('/api/v1/workshop/vehicles', [
            'chassis' => self::OWNERLESS_CHASSIS, 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021,
        ])->assertCreated()->assertJsonPath('data.has_owner', false);

        $vehicle = Vehicle::findByChassis(self::OWNERLESS_CHASSIS);
        $this->assertNull($vehicle->license_plate);
        $this->assertNull($vehicle->renavam);
    }

    public function test_duplicate_chassis_returns_409_with_the_minimal_payload(): void
    {
        $vehicle = $this->ownerlessVehicle(['brand' => 'Ferrari']);
        $this->ownerOf($vehicle);
        $this->actingAsApiUser($this->workshopAccount());

        $response = $this->postJson('/api/v1/workshop/vehicles', [
            'chassis' => self::OWNERLESS_CHASSIS, 'brand' => 'X', 'model' => 'Y', 'year' => 2020,
        ])->assertStatus(409);

        $this->assertSame(['id' => $vehicle->id, 'has_owner' => true], $response->json('data.vehicle'));
        $this->assertStringNotContainsString('Ferrari', $response->getContent());
        $this->assertSame(1, Vehicle::count());
    }

    public function test_os_on_an_ownerless_vehicle_returns_the_ownerless_fields_and_no_tenant(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $this->actingAsApiUser($workshop);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 45_000,
            'service_category' => 'mechanical',
        ])->assertCreated()
            ->assertJsonPath('data.is_ownerless_record', true)
            ->assertJsonPath('data.owner_status', 'pending')
            ->assertJsonPath('data.attachments_status', 'none')
            ->assertJsonPath('data.hidden_from_public', false);

        $this->assertNull(Maintenance::query()->firstOrFail()->tenant_id);
    }

    public function test_os_on_an_owned_vehicle_is_not_flagged_ownerless(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $owner = $this->ownerOf($vehicle);
        $this->actingAsApiUser($this->workshopAccount());

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 45_000,
            'service_category' => 'mechanical',
        ])->assertCreated()
            ->assertJsonPath('data.is_ownerless_record', false)
            ->assertJsonPath('data.owner_status', null);

        $this->assertSame($owner->tenant_id, Maintenance::query()->firstOrFail()->tenant_id);
    }
}
