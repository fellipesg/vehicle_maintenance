<?php

namespace Tests\Feature;

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
