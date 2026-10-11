<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtagForVehicleListTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_request_returns_etag_and_second_matching_request_returns_304(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $first = $this->getJson('/api/v1/my-vehicles');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $second = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/my-vehicles');
        $second->assertStatus(304);
        $this->assertSame($etag, $second->headers->get('ETag'));
    }

    public function test_vehicle_update_changes_etag(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create(['color' => 'Branco']);
        $this->attachVehicleToUser($user, $vehicle);

        $first = $this->getJson('/api/v1/my-vehicles');
        $etagBefore = $first->headers->get('ETag');

        $this->travel(2)->seconds();
        $vehicle->touch();

        $second = $this->getJson('/api/v1/my-vehicles');
        $etagAfter = $second->headers->get('ETag');

        $this->assertNotSame($etagBefore, $etagAfter);
    }

    /**
     * A listagem traz maintenances_count e verified_maintenances_count, que mudam sem tocar em
     * vehicles.updated_at: uma manutenção registrada em outro aparelho respondia 304 e o app
     * seguia mostrando a contagem velha.
     */
    public function test_new_maintenance_changes_etag(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $etagBefore = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $etagAfter = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        $this->assertNotSame($etagBefore, $etagAfter);
    }

    public function test_deleting_a_maintenance_changes_etag(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $etagBefore = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        $maintenance->delete();

        $etagAfter = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        $this->assertNotSame($etagBefore, $etagAfter);
    }

    public function test_maintenance_of_another_vehicle_does_not_change_etag(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $etagBefore = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        // Veículo que não é do usuário: a listagem dele não muda.
        $otherVehicle = Vehicle::factory()->create();
        Maintenance::factory()->create([
            'vehicle_id' => $otherVehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $etagAfter = $this->getJson('/api/v1/my-vehicles')->headers->get('ETag');

        $this->assertSame($etagBefore, $etagAfter);
    }

    public function test_include_query_changes_etag(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $default = $this->getJson('/api/v1/my-vehicles');
        $withInclude = $this->getJson('/api/v1/my-vehicles?include=plates');

        $this->assertNotSame(
            $default->headers->get('ETag'),
            $withInclude->headers->get('ETag'),
        );
    }
}
