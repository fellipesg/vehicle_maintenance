<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_testing_allows_wildcard_origin_when_env_not_set(): void
    {
        $this->assertSame(['*'], config('cors.allowed_origins'));
        $this->assertFalse(config('cors.supports_credentials'));

        $response = $this->getJson('/api/v1/workshops', [
            'Origin' => 'http://localhost:3000',
        ]);

        $response->assertOk();
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_explicit_allowed_origins_disable_wildcard_credentials(): void
    {
        Config::set('cors.allowed_origins', ['https://app.example.com']);
        Config::set('cors.supports_credentials', true);

        $this->assertSame(['https://app.example.com'], config('cors.allowed_origins'));
        $this->assertTrue(config('cors.supports_credentials'));
    }
}
