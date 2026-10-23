<?php

namespace Tests\Feature\Api;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * A foto da manutenção sai por GET /api/v1/maintenance-photos/{photo}
 * (MaintenancePhotoPolicy::view), e o MaintenancePhotoResource não devolve mais caminho nem URL do
 * storage: lá a foto abre sem token (disco público) ou por URL assinada compartilhável (S3/R2).
 */
class MaintenancePhotoDownloadTest extends TestCase
{
    use BuildsOwnerlessRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_owner_downloads_the_photo_of_their_maintenance(): void
    {
        $owner = $this->actingAsApiUser();
        $photo = $this->photoFor($this->maintenanceOf($owner));

        $response = $this->get(route('api.maintenance-photos.show', $photo));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString($photo->original_name, (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('nosniff', (string) $response->headers->get('x-content-type-options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('content-security-policy'));
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('cache-control'));
    }

    public function test_guest_is_unauthenticated(): void
    {
        $photo = $this->photoFor($this->maintenanceOf(User::factory()->asUser()->create()->refresh()));

        $this->getJson(route('api.maintenance-photos.show', $photo))->assertUnauthorized();
    }

    public function test_another_account_cannot_download_the_photo(): void
    {
        $photo = $this->photoFor($this->maintenanceOf(User::factory()->asUser()->create()->refresh()));

        $this->actingAsApiUser();

        $this->getJson(route('api.maintenance-photos.show', $photo))->assertForbidden();
    }

    public function test_unverified_owner_cannot_download_pending_attachment_of_ownerless_record(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshopUser, $vehicle);
        $photo = $this->photoFor($maintenance);
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAsApiUser($owner);
        $this->getJson(route('api.maintenance-photos.show', $photo))->assertForbidden();

        // A oficina autora vê o anexo antes do aceite; é só o dono que espera.
        $this->actingAsApiUser($workshopUser);
        $this->get(route('api.maintenance-photos.show', $photo))->assertOk();
    }

    public function test_missing_file_returns_not_found(): void
    {
        $owner = $this->actingAsApiUser();
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenanceOf($owner)->id,
            'path' => 'maintenance-photos/sumiu.jpg',
            'original_name' => 'sumiu.jpg',
            'created_by' => $owner->id,
        ]);

        $this->getJson(route('api.maintenance-photos.show', $photo))->assertNotFound();
    }

    public function test_unknown_extension_is_downloaded_instead_of_opened_inline(): void
    {
        $owner = $this->actingAsApiUser();
        $photo = $this->photoFor($this->maintenanceOf($owner), 'foto.heic');

        $response = $this->get(route('api.maintenance-photos.show', $photo));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/octet-stream');
        $response->assertDownload('foto.heic');
    }

    public function test_maintenance_resource_returns_the_route_and_no_storage_path(): void
    {
        $owner = $this->actingAsApiUser();
        $maintenance = $this->maintenanceOf($owner);
        $photo = $this->photoFor($maintenance);

        $response = $this->getJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertOk();
        $response->assertJsonPath('data.photos.0.url', route('api.maintenance-photos.show', $photo));
        $response->assertJsonMissingPath('data.photos.0.path');
        $response->assertDontSee($photo->path, false);
    }

    private function maintenanceOf(User $owner): Maintenance
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        return Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->fresh()->tenant_id,
        ]);
    }

    private function photoFor(
        Maintenance $maintenance,
        string $fileName = 'foto_do_servico.jpg',
        string $contents = 'bytes-da-foto',
    ): MaintenancePhoto {
        $path = 'maintenance-photos/'.$maintenance->id.'_'.pathinfo($fileName, PATHINFO_FILENAME)
            .'.'.pathinfo($fileName, PATHINFO_EXTENSION);
        Storage::disk('public')->put($path, $contents);

        return MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'path' => $path,
            'original_name' => $fileName,
            'created_by' => $maintenance->user_id,
        ]);
    }
}
