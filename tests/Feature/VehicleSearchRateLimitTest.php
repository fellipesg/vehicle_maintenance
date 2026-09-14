<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class VehicleSearchRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('search');
    }

    public function test_twenty_first_search_request_in_a_minute_returns_429(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'SRCH123']);

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->getJson('/api/v1/vehicles/search/'.$vehicle->license_plate)
                ->assertOk();
        }

        $this->getJson('/api/v1/vehicles/search/'.$vehicle->license_plate)
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
