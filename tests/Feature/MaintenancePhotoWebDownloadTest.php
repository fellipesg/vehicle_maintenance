<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * A foto da manutenção passa pela rota maintenance-photos.show (MaintenancePhotoPolicy::view),
 * nunca pelo caminho do arquivo no storage.
 */
class MaintenancePhotoWebDownloadTest extends TestCase
{
    use BuildsOwnerlessRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_owner_opens_the_photo_of_their_maintenance(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = $this->photoFor($this->maintenanceOf($owner));

        $response = $this->actingAs($owner)->get(route('maintenance-photos.show', $photo));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString($photo->original_name, (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('nosniff', (string) $response->headers->get('x-content-type-options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('content-security-policy'));
        // Galeria de miniaturas: cache privado e curto, nunca no-store nem público.
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('cache-control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('cache-control'));
    }

    public function test_unknown_extension_is_downloaded_instead_of_opened_in_the_tab(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = $this->photoFor($this->maintenanceOf($owner), 'foto.heic');

        $response = $this->actingAs($owner)->get(route('maintenance-photos.show', $photo));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/octet-stream');
        $response->assertDownload('foto.heic');
    }

    public function test_another_account_cannot_open_the_photo(): void
    {
        $photo = $this->photoFor($this->maintenanceOf(User::factory()->asUser()->create()));
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)
            ->get(route('maintenance-photos.show', $photo))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $photo = $this->photoFor($this->maintenanceOf(User::factory()->asUser()->create()));

        $this->get(route('maintenance-photos.show', $photo))->assertRedirect(route('login'));
    }

    public function test_owner_cannot_open_pending_attachment_of_ownerless_record(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshopUser, $vehicle);
        $photo = $this->photoFor($maintenance);
        $owner = $this->ownerOf($vehicle);

        $this->actingAs($owner)
            ->get(route('maintenance-photos.show', $photo))
            ->assertForbidden();

        $this->actingAs($workshopUser)
            ->get(route('maintenance-photos.show', $photo))
            ->assertOk();
    }

    public function test_missing_file_returns_not_found(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenanceOf($owner)->id,
            'path' => 'maintenance-photos/sumiu.jpg',
            'original_name' => 'sumiu.jpg',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('maintenance-photos.show', $photo))
            ->assertNotFound();
    }

    public function test_maintenance_detail_links_to_the_route_not_to_the_storage_path(): void
    {
        $owner = User::factory()->asUser()->create();
        $maintenance = $this->maintenanceOf($owner);
        $photo = $this->photoFor($maintenance);

        $response = $this->actingAs($owner)->get(route('user.maintenances.show', $maintenance));

        $response->assertOk();
        $response->assertSee(route('maintenance-photos.show', $photo), false);
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
