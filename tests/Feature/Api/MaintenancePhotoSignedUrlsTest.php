<?php

namespace Tests\Feature\Api;

use App\Http\Resources\Api\V1\MaintenancePhotoResource;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\Feature\Concerns\BuildsPhotoFixtures;
use Tests\TestCase;

/**
 * variants/expires_at do MaintenancePhotoResource: URLs temporárias só depois da
 * MaintenancePhotoPolicy::view, estáveis dentro de uma janela de 10 minutos, nunca com o caminho.
 */
class MaintenancePhotoSignedUrlsTest extends TestCase
{
    use BuildsOwnerlessRecords, BuildsPhotoFixtures, RefreshDatabase;

    private User $owner;

    private Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(now()->startOfHour()->addMinutes(3));

        $this->owner = User::factory()->asUser()->create()->refresh();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($this->owner, $vehicle);
        $this->maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);
    }

    private function processedPhoto(?Maintenance $maintenance = null): MaintenancePhoto
    {
        $photo = MaintenancePhoto::factory()->processed()->create([
            'maintenance_id' => ($maintenance ?? $this->maintenance)->id,
            'created_by' => $this->owner->id,
        ]);

        foreach ([$photo->path, $photo->thumb_path, $photo->display_path] as $path) {
            Storage::disk('public')->put($path, str_ends_with($path, '.webp') ? $this->plainJpeg(40, 30) : $this->plainJpeg(80, 60));
        }

        return $photo;
    }

    public function test_authorized_viewer_gets_variants_and_never_a_storage_path(): void
    {
        $photo = $this->processedPhoto();
        $this->actingAsApiUser($this->owner);

        $response = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->assertOk();

        $data = $response->json('data.photos.0');
        $this->assertSame(['thumb', 'display', 'master'], array_keys($data['variants']));
        $this->assertTrue($data['processed']);
        $this->assertSame(2048, $data['width']);
        $this->assertSame('#6b7a8f', $data['dominant_color']);
        $this->assertSame(route('api.maintenance-photos.show', $photo), $data['url']);
        $this->assertNotSame($data['variants']['thumb'], $data['variants']['master']);
        $this->assertNotNull($data['expires_at']);
        foreach (['path', 'thumb_path', 'display_path'] as $key) {
            $response->assertJsonMissingPath("data.photos.0.{$key}");
        }
        $response->assertDontSee($photo->path, false);
        $response->assertDontSee($photo->thumb_path, false);
    }

    public function test_unprocessed_photo_uses_the_master_url_for_thumb_and_display(): void
    {
        $photo = MaintenancePhoto::factory()->create([
            'maintenance_id' => $this->maintenance->id,
            'path' => 'maintenance-photos/legado.jpg',
        ]);
        Storage::disk('public')->put($photo->path, $this->plainJpeg());
        $this->actingAsApiUser($this->owner);

        $variants = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->json('data.photos.0.variants');

        $this->assertSame($variants['master'], $variants['thumb']);
        $this->assertSame($variants['master'], $variants['display']);
    }

    public function test_variants_are_omitted_for_a_viewer_who_fails_the_policy(): void
    {
        $photo = $this->processedPhoto()->load('maintenance');
        $stranger = User::factory()->asUser()->create()->refresh();

        $asStranger = (new MaintenancePhotoResource($photo))->forViewer($stranger)->resolve(Request::create('/'));
        $asGuest = (new MaintenancePhotoResource($photo))->forViewer(null)->resolve(Request::create('/'));
        $asOwner = (new MaintenancePhotoResource($photo))->forViewer($this->owner)->resolve(Request::create('/'));

        foreach ([$asStranger, $asGuest] as $payload) {
            $this->assertArrayNotHasKey('variants', $payload);
            $this->assertArrayNotHasKey('expires_at', $payload);
            $this->assertArrayNotHasKey('path', $payload);
        }
        $this->assertArrayHasKey('variants', $asOwner);
    }

    public function test_the_same_window_returns_identical_urls_and_the_next_window_new_ones(): void
    {
        $this->processedPhoto();
        $this->actingAsApiUser($this->owner);
        $uri = "/api/v1/maintenances/{$this->maintenance->id}";

        $first = $this->getJson($uri)->json('data.photos.0');
        $this->travel(2)->minutes();
        $second = $this->getJson($uri)->json('data.photos.0');

        $this->assertSame($first['variants'], $second['variants']);
        $this->assertSame($first['expires_at'], $second['expires_at']);

        $this->travel(10)->minutes();
        $third = $this->getJson($uri)->json('data.photos.0');

        $this->assertNotSame($first['variants']['thumb'], $third['variants']['thumb']);
        $this->assertGreaterThan(strtotime($first['expires_at']), strtotime($third['expires_at']));
    }

    public function test_signed_url_serves_the_variant_without_authentication(): void
    {
        $photo = $this->processedPhoto();
        $this->actingAsApiUser($this->owner);
        $variants = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->json('data.photos.0.variants');

        $this->app['auth']->forgetGuards();
        $response = $this->get($variants['thumb']);

        $response->assertOk();
        $response->assertHeader('content-type', 'image/webp');
        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertSame('thumb.webp', basename($photo->thumb_path));
        $this->get($variants['master'])->assertOk()->assertHeader('content-type', 'image/jpeg');
    }

    public function test_signed_url_is_scoped_to_one_photo_and_variant_and_expires(): void
    {
        $photo = $this->processedPhoto();
        $other = $this->processedPhoto();
        $this->actingAsApiUser($this->owner);
        $variants = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->json('data.photos.0.variants');
        $this->app['auth']->forgetGuards();

        $otherPhotoUrl = str_replace("/maintenance-photo-files/{$photo->id}/", "/maintenance-photo-files/{$other->id}/", $variants['thumb']);
        $otherVariantUrl = str_replace('/thumb?', '/master?', $variants['thumb']);
        $unsignedUrl = strtok($variants['thumb'], '?');

        $this->get($otherPhotoUrl)->assertForbidden();
        $this->get($otherVariantUrl)->assertForbidden();
        $this->get($unsignedUrl)->assertForbidden();

        $this->travel(21)->minutes();
        $this->get($variants['thumb'])->assertForbidden();
    }

    public function test_pending_ownerless_photos_are_locked_for_everyone_but_the_author_and_the_verified_owner(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshopUser, $vehicle);
        $photo = MaintenancePhoto::factory()->processed()->create(['maintenance_id' => $record->id, 'created_by' => $workshopUser->id])->load('maintenance');
        $viewers = [
            'author' => [$workshopUser, true],
            'verified owner' => [$this->ownerOf($vehicle), true],
            'unverified owner' => [$this->ownerOf($vehicle, verified: false), false],
            'stranger' => [User::factory()->asUser()->create()->refresh(), false],
            'guest' => [null, false],
        ];

        foreach ($viewers as $label => [$viewer, $expected]) {
            $payload = (new MaintenancePhotoResource($photo))->forViewer($viewer)->resolve(Request::create('/'));

            $this->assertSame($expected, isset($payload['variants']), $label);
        }
    }
}
