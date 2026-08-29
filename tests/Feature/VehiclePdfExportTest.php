<?php

namespace Tests\Feature;

use App\Jobs\GenerateVehicleMaintenancePdfExport;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class VehiclePdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_queues_export_and_returns_202_with_export_id(): void
    {
        Queue::fake();

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $response = $this->postJson("/api/v1/vehicles/{$vehicle->id}/export-pdf");

        $response->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PDF export queued.')
            ->assertJsonPath('data.status', VehiclePdfExport::STATUS_PENDING)
            ->assertJsonStructure([
                'data' => ['export_id', 'status', 'status_url'],
            ]);

        $exportId = $response->json('data.export_id');
        $this->assertNotEmpty($exportId);

        $this->assertDatabaseHas('vehicle_pdf_exports', [
            'id' => $exportId,
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'status' => VehiclePdfExport::STATUS_PENDING,
        ]);

        Queue::assertPushed(GenerateVehicleMaintenancePdfExport::class, function (GenerateVehicleMaintenancePdfExport $job) use ($exportId): bool {
            return $job->exportId === $exportId;
        });
    }

    public function test_get_status_returns_pending_then_completed(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $queued = $this->postJson("/api/v1/vehicles/{$vehicle->id}/export-pdf")
            ->assertAccepted();

        $exportId = $queued->json('data.export_id');

        $this->getJson("/api/v1/vehicle-pdf-exports/{$exportId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.export_id', $exportId)
            ->assertJsonPath('data.status', VehiclePdfExport::STATUS_COMPLETED)
            ->assertJsonStructure([
                'data' => [
                    'export_id',
                    'status',
                    'filename',
                    'download_url',
                    'download_url_expires_at',
                    'expires_at',
                    'completed_at',
                ],
            ]);

        $export = VehiclePdfExport::query()->findOrFail($exportId);
        $this->assertNotNull($export->file_path);
        Storage::disk('public')->assertExists($export->file_path);
    }

    public function test_unauthorized_user_cannot_access_export_status(): void
    {
        $owner = User::factory()->asUser()->create();
        $otherUser = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $export = VehiclePdfExport::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_completed_export_returns_download_url(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $path = 'exports/vehicle-pdfs/test-export.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test');

        $export = VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => $path,
        ]);

        $response = $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.status', VehiclePdfExport::STATUS_COMPLETED);

        $downloadUrl = $response->json('data.download_url');
        $this->assertIsString($downloadUrl);
        $this->assertNotSame('', $downloadUrl);
        $this->assertNotNull($response->json('data.download_url_expires_at'));
        $this->assertSame(
            "/api/v1/vehicle-pdf-exports/{$export->id}/download",
            $response->json('data.download_api_url'),
        );
    }

    public function test_completed_export_can_be_downloaded_as_attachment(): void
    {
        Storage::fake('public');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $path = 'exports/vehicle-pdfs/test-export.pdf';
        $pdfContents = '%PDF-1.4 test';
        Storage::disk('public')->put($path, $pdfContents);

        $export = VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => $path,
            'filename' => 'historico_teste.pdf',
        ]);

        $this->get("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('historico_teste.pdf')
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame($pdfContents, Storage::disk('public')->get($path));
    }

    public function test_old_sync_get_export_endpoint_is_gone(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/export-pdf")
            ->assertMethodNotAllowed();
    }

    public function test_failed_export_does_not_leak_exception_message_in_production(): void
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

        Bus::assertDispatched(GenerateVehicleMaintenancePdfExport::class, function (GenerateVehicleMaintenancePdfExport $job) use ($exportId): bool {
            return $job->exportId === $exportId;
        });

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
