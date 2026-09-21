<?php

namespace Tests\Feature;

use App\Jobs\GenerateVehicleMaintenancePdfExport;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

    public function test_docs_basic_auth_rejects_wrong_username_or_password(): void
    {
        Config::set('api-docs.username', 'qa');
        Config::set('api-docs.password', 'secret');

        $this->withBasicAuth('qa', 'wrong')->get('/docs/api')->assertUnauthorized();
        $this->withBasicAuth('other', 'secret')->get('/docs/api')->assertUnauthorized();
    }

    public function test_docs_return_404_in_production_when_credentials_not_configured(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/docs/api')->assertNotFound();
        $this->getJson('/docs/api.json')->assertNotFound();
    }

    public function test_docs_require_basic_auth_in_production_when_credentials_configured(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('api-docs.username', 'qa');
        Config::set('api-docs.password', 'secret');

        $this->get('/docs/api')->assertUnauthorized();

        $this->withBasicAuth('qa', 'secret')
            ->getJson('/docs/api.json')
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

    public function test_openapi_spec_documents_provenance_and_plate_identity_fields(): void
    {
        $spec = json_encode($this->getJson('/docs/api.json')->json());
        $needles = [
            'verified',
            'provenance_strip',
            'maintenances_count',
            'verified_maintenances_count',
            'verification_url',
            'verification_qr_matrix',
            'matched_by',
            'plate_history',
            'vehicle.plates',
        ];

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $spec, "OpenAPI spec should mention {$needle}");
        }
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
        Bus::fake();

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $mock = Mockery::mock(VehicleMaintenancePdfExporter::class);
        $mock->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Sensitive internal failure details'));
        $mock->shouldReceive('cleanupTemps')->zeroOrMoreTimes();
        $this->app->instance(VehicleMaintenancePdfExporter::class, $mock);

        $queued = $this->postJson("/api/v1/vehicles/{$vehicle->id}/export-pdf")
            ->assertAccepted();

        $exportId = $queued->json('data.export_id');

        Bus::assertDispatched(GenerateVehicleMaintenancePdfExport::class);

        try {
            (new GenerateVehicleMaintenancePdfExport($exportId))->handle($mock);
        } catch (\RuntimeException) {
            // Expected on first attempt.
        }

        (new GenerateVehicleMaintenancePdfExport($exportId))->failed(
            new \RuntimeException('Sensitive internal failure details')
        );

        $this->getJson("/api/v1/vehicle-pdf-exports/{$exportId}")
            ->assertOk()
            ->assertJsonPath('data.status', VehiclePdfExport::STATUS_FAILED)
            ->assertJsonPath('data.error_message', 'Unable to generate PDF. Please try again later.');
    }
}
