<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebSessionVehiclePdfExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_session_user_can_download_via_portal_route(): void
    {
        Storage::fake('public');

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $path = 'exports/vehicle-pdfs/test-export.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test');

        $export = VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => $path,
            'filename' => 'historico_teste.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('user.vehicle-pdf-exports.download', [
                'export' => $export,
                'filename' => 'historico_teste.pdf',
            ]))
            ->assertOk()
            ->assertDownload('historico_teste.pdf')
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_legacy_baixar_path_redirects_to_filename_with_pdf_extension(): void
    {
        Storage::fake('public');

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $path = 'exports/vehicle-pdfs/test-export.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test');

        $export = VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => $path,
            'filename' => 'historico_teste.pdf',
        ]);

        $response = $this->actingAs($user)
            ->get('/usuario/exportacoes-pdf/'.$export->id.'/baixar');

        $response->assertRedirect();
        $this->assertSame(
            '/usuario/exportacoes-pdf/'.$export->id.'/historico_teste.pdf',
            $response->headers->get('Location'),
        );
    }

    public function test_web_session_user_can_download_via_api_route(): void
    {
        Storage::fake('public');

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $path = 'exports/vehicle-pdfs/test-export.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 test');

        $export = VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => $path,
            'filename' => 'historico_teste.pdf',
        ]);

        $this->actingAs($user)
            ->get("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('historico_teste.pdf')
            ->assertHeader('content-type', 'application/pdf');
    }
}
