<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Maintenance\MaintenancePhotoUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\Feature\Concerns\BuildsPhotoFixtures;
use Tests\TestCase;

/**
 * As telas do portal mostram a foto pelas variantes em URL temporária (miniatura = thumb,
 * lightbox = display, "abrir" = master), assinadas só depois da MaintenancePhotoPolicy::view e
 * sem nunca expor o caminho no storage.
 */
class MaintenancePhotoWebVariantsTest extends TestCase
{
    use BuildsOwnerlessRecords, BuildsPhotoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(now()->startOfHour()->addMinutes(3));
    }

    public function test_detail_uses_thumb_for_the_list_display_for_the_lightbox_and_master_for_the_link(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = $this->processedPhoto($this->maintenanceOf($owner), $owner);
        $urls = MaintenancePhotoUrls::variants($photo);

        $response = $this->actingAs($owner)->get(route('user.maintenances.show', $photo->maintenance_id));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('<img src="'.e($urls['thumb']).'"', $html);
        $this->assertStringContainsString('href="'.e($urls['master']).'"', $html);
        $this->assertStringContainsString('data-lightbox-src="'.e($urls['master']).'"', $html);
        $this->assertStringContainsString('src="'.e($urls['display']).'"', $html);
        $this->assertStringNotContainsString(route('maintenance-photos.show', $photo), $html);
    }

    public function test_detail_never_leaks_storage_paths(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = $this->processedPhoto($this->maintenanceOf($owner), $owner);

        $html = $this->actingAs($owner)->get(route('user.maintenances.show', $photo->maintenance_id))->getContent();

        foreach ([$photo->path, $photo->thumb_path, $photo->display_path] as $path) {
            $this->assertStringNotContainsString($path, $html);
        }
    }

    public function test_unprocessed_photo_falls_back_to_the_master_url(): void
    {
        $owner = User::factory()->asUser()->create();
        $maintenance = $this->maintenanceOf($owner);
        $photo = MaintenancePhoto::factory()->create(['maintenance_id' => $maintenance->id, 'created_by' => $owner->id]);
        Storage::disk('public')->put($photo->path, $this->plainJpeg());
        $urls = MaintenancePhotoUrls::variants($photo);

        $this->assertSame($urls['master'], $urls['thumb']);
        $this->actingAs($owner)
            ->get(route('user.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('<img src="'.e($urls['master']).'"', false);
    }

    public function test_viewer_who_fails_the_policy_gets_no_urls(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshopUser, $vehicle);
        $photo = $this->processedPhoto($maintenance, $workshopUser);
        $unverifiedOwner = $this->ownerOf($vehicle, verified: false);

        $this->assertNull(MaintenancePhotoUrls::forViewer($photo, $unverifiedOwner));
        $this->assertNull(MaintenancePhotoUrls::forViewer($photo, null));
        $this->assertNull(MaintenancePhotoUrls::forViewer($photo, User::factory()->asUser()->create()));
        $this->assertIsArray(MaintenancePhotoUrls::forViewer($photo, $workshopUser));
    }

    public function test_unauthorized_viewer_page_carries_no_photo_urls(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshopUser, $vehicle);
        $photo = $this->processedPhoto($maintenance, $workshopUser);
        $urls = MaintenancePhotoUrls::variants($photo);
        $owner = $this->ownerOf($vehicle, verified: false);

        $response = $this->actingAs($owner)->get(route('user.maintenances.show', $maintenance));

        foreach ([$urls['thumb'], $urls['display'], $urls['master']] as $url) {
            $this->assertStringNotContainsString(e($url), $response->getContent());
        }
    }

    public function test_workshop_edit_form_lists_saved_photos_with_thumb(): void
    {
        $workshopUser = $this->workshopAccount();
        $maintenance = $this->ownerlessRecord($workshopUser, $this->ownerlessVehicle());
        $photo = $this->processedPhoto($maintenance, $workshopUser);
        $urls = MaintenancePhotoUrls::variants($photo);

        $response = $this->withoutVite()
            ->actingAs($workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance));

        $response->assertOk();
        $response->assertSee('<img src="'.e($urls['thumb']).'"', false);
        $response->assertSee('href="'.e($urls['master']).'"', false);
        $response->assertDontSee($photo->path, false);
    }

    public function test_falls_back_to_the_authorized_route_when_signing_fails(): void
    {
        $owner = User::factory()->asUser()->create();
        $photo = $this->processedPhoto($this->maintenanceOf($owner), $owner);
        $photo->loadMissing('maintenance');
        Cache::shouldReceive('remember')->once()->andThrow(new RuntimeException('signing failed'));

        $urls = MaintenancePhotoUrls::forViewer($photo, $owner);

        $this->assertSame(route('maintenance-photos.show', $photo), $urls['thumb']);
        $this->assertSame($urls['thumb'], $urls['master']);
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

    private function processedPhoto(Maintenance $maintenance, User $author): MaintenancePhoto
    {
        $photo = MaintenancePhoto::factory()->processed()->create([
            'maintenance_id' => $maintenance->id,
            'created_by' => $author->id,
        ]);

        foreach ([$photo->path, $photo->thumb_path, $photo->display_path] as $path) {
            Storage::disk('public')->put($path, $this->plainJpeg(80, 60));
        }

        return $photo;
    }
}
