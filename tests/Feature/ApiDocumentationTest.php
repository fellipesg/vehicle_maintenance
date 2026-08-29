<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('api-docs.enabled', true);
        Config::set('api-docs.username', null);
        Config::set('api-docs.password', null);
    }

    public function test_docs_json_endpoint_returns_200_when_enabled(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['openapi', 'info', 'paths']);
    }

    public function test_docs_ui_returns_200_when_enabled(): void
    {
        $this->get('/docs/api')
            ->assertOk();
    }

    public function test_docs_returns_404_when_disabled(): void
    {
        Config::set('api-docs.enabled', false);

        $this->get('/docs/api')->assertNotFound();
        $this->getJson('/docs/api.json')->assertNotFound();
    }

    public function test_docs_basic_auth_works_when_credentials_configured(): void
    {
        Config::set('api-docs.username', 'qa');
        Config::set('api-docs.password', 'secret');

        $this->get('/docs/api')->assertUnauthorized();

        $this->withBasicAuth('qa', 'secret')
            ->get('/docs/api')
            ->assertOk();
    }

    public function test_openapi_spec_contains_login_and_security_scheme(): void
    {
        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $spec = $response->json();

        $this->assertArrayHasKey('/login', $spec['paths']);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);

        $schemes = $spec['components']['securitySchemes'];
        $this->assertNotEmpty($schemes);

        $hasBearer = collect($schemes)->contains(function (array $scheme): bool {
            return ($scheme['type'] ?? null) === 'http'
                && ($scheme['scheme'] ?? null) === 'bearer';
        });

        $this->assertTrue($hasBearer);
    }

    public function test_unauthenticated_api_requests_return_json_401(): void
    {
        $this->getJson('/api/v1/vehicles')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_oauth_callback_does_not_leak_exception_message_in_production(): void
    {
        Config::set('app.debug', false);

        $response = $this->getJson('/api/v1/auth/google/callback');

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to authenticate with the selected provider.');
    }

    public function test_export_pdf_does_not_leak_exception_message_in_production(): void
    {
        Config::set('app.debug', false);

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $mock = Mockery::mock(VehicleMaintenancePdfExporter::class);
        $mock->shouldReceive('download')
            ->once()
            ->andThrow(new \RuntimeException('Sensitive internal failure details'));
        $this->app->instance(VehicleMaintenancePdfExporter::class, $mock);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/export-pdf")
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to generate PDF. Please try again later.');
    }
}
