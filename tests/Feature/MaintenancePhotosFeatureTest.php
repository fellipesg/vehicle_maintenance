<?php

namespace Tests\Feature;

use App\Features\MaintenancePhotos;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Maintenance\MaintenancePhotoAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\Feature\Concerns\BuildsPhotoFixtures;
use Tests\TestCase;

/**
 * Fotos das manutenções são uma funcionalidade por conta (Pennant), desligada por padrão: sem ela
 * não há envio, visualização nem foto no PDF. Aqui o recurso NÃO é ligado para todos; cada teste
 * liga (ou não) a conta, a oficina ou a loja.
 */
class MaintenancePhotosFeatureTest extends TestCase
{
    use BuildsOwnerlessRecords, BuildsPhotoFixtures, RefreshDatabase;

    private User $owner;

    private Vehicle $vehicle;

    private Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(now()->startOfHour()->addMinutes(3));

        $this->owner = User::factory()->asUser()->create()->refresh();
        $this->vehicle = Vehicle::factory()->create(['current_kilometers' => 90_000]);
        $this->attachVehicleToUser($this->owner, $this->vehicle);
        $this->maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
            'kilometers' => 50_000,
        ]);
    }

    private function photoOf(Maintenance $maintenance, string $stage = 'after'): MaintenancePhoto
    {
        $photo = MaintenancePhoto::factory()->processed()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => 'vehicle',
            'stage' => $stage,
            'created_by' => $maintenance->user_id,
        ]);

        foreach ([$photo->path, $photo->thumb_path, $photo->display_path] as $path) {
            Storage::disk('public')->put($path, $this->plainJpeg(80, 60));
        }

        return $photo;
    }

    public function test_feature_is_off_by_default_for_every_kind_of_account(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();
        $garageUser = User::factory()->asGarage()->create()->refresh();

        foreach ([$this->owner, $workshopUser, $garageUser, User::factory()->asAdmin()->create()] as $user) {
            $this->assertFalse(MaintenancePhotoAccess::allows($user), $user->user_type);
        }

        $this->assertFalse(MaintenancePhotoAccess::allows(null));
        $this->assertFalse(Feature::for($workshopUser->workshop)->active(MaintenancePhotos::class));
        $this->assertFalse(Feature::for($garageUser->garage)->active(MaintenancePhotos::class));
    }

    public function test_activating_a_user_affects_only_that_user(): void
    {
        $other = User::factory()->asUser()->create()->refresh();

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->assertTrue(MaintenancePhotoAccess::allows($this->owner));
        $this->assertFalse(MaintenancePhotoAccess::allows($other));

        Feature::for($this->owner)->deactivate(MaintenancePhotos::class);

        $this->assertFalse(MaintenancePhotoAccess::allows($this->owner));
    }

    public function test_activating_a_workshop_gives_the_feature_to_its_user_only(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();
        $otherWorkshopUser = User::factory()->asWorkshop()->create()->refresh();

        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $this->assertTrue(MaintenancePhotoAccess::allows($workshopUser->fresh()));
        $this->assertFalse(MaintenancePhotoAccess::allows($otherWorkshopUser->fresh()));
        $this->assertFalse(MaintenancePhotoAccess::allows($this->owner));
    }

    public function test_activating_a_garage_gives_the_feature_to_its_user_only(): void
    {
        $garageUser = User::factory()->asGarage()->create()->refresh();
        $otherGarageUser = User::factory()->asGarage()->create()->refresh();

        Feature::for($garageUser->garage)->activate(MaintenancePhotos::class);

        $this->assertTrue(MaintenancePhotoAccess::allows($garageUser->fresh()));
        $this->assertFalse(MaintenancePhotoAccess::allows($otherGarageUser->fresh()));
    }

    public function test_user_flag_or_workshop_flag_is_enough(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create()->refresh();

        Feature::for($workshopUser)->activate(MaintenancePhotos::class);
        $this->assertTrue(MaintenancePhotoAccess::allows($workshopUser->fresh()));

        Feature::for($workshopUser)->deactivate(MaintenancePhotos::class);
        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);
        $this->assertTrue(MaintenancePhotoAccess::allows($workshopUser->fresh()));

        Feature::for($workshopUser->workshop)->deactivate(MaintenancePhotos::class);
        $this->assertFalse(MaintenancePhotoAccess::allows($workshopUser->fresh()));
    }

    public function test_policy_denies_view_create_and_delete_without_the_feature(): void
    {
        $photo = $this->photoOf($this->maintenance);
        $photo->setRelation('maintenance', $this->maintenance);

        $this->assertTrue(Gate::forUser($this->owner)->denies('view', $photo));
        $this->assertTrue(Gate::forUser($this->owner)->denies('create', [MaintenancePhoto::class, $this->maintenance]));
        $this->assertTrue(Gate::forUser($this->owner)->denies('delete', $photo));

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->assertTrue(Gate::forUser($this->owner->fresh())->allows('view', $photo));
        $this->assertTrue(Gate::forUser($this->owner->fresh())->allows('create', [MaintenancePhoto::class, $this->maintenance]));
        $this->assertTrue(Gate::forUser($this->owner->fresh())->allows('delete', $photo));
    }

    public function test_api_upload_is_forbidden_without_the_feature_and_allowed_with_it(): void
    {
        $workshopUser = $this->workshopAccount();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshopUser->workshop->id]);
        $payload = fn (): array => ['photo' => UploadedFile::fake()->image('antes.jpg'), 'subject' => 'vehicle', 'stage' => 'before'];

        $this->actingAsApiUser($workshopUser);
        $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", $payload())->assertForbidden();
        $this->assertSame(0, MaintenancePhoto::query()->where('maintenance_id', $maintenance->id)->count());

        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($workshopUser->fresh());
        $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", $payload())->assertCreated();
        $this->assertSame(1, MaintenancePhoto::query()->where('maintenance_id', $maintenance->id)->count());
    }

    public function test_api_maintenance_hides_photos_from_a_viewer_without_the_feature(): void
    {
        $this->photoOf($this->maintenance);

        $this->actingAsApiUser($this->owner);
        $json = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->assertOk()->json('data');

        $this->assertSame([], $json['photos']);
        $this->assertSame(0, $json['photos_count']);
        $this->assertFalse($json['photos_locked']);
        $this->assertStringNotContainsString('variants', json_encode($json));

        $list = $this->getJson('/api/v1/maintenances')->assertOk()->json('data');
        $this->assertStringNotContainsString('"variants"', json_encode($list));
    }

    public function test_api_maintenance_shows_photos_to_a_viewer_with_the_feature(): void
    {
        $this->photoOf($this->maintenance);
        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($this->owner->fresh());
        $json = $this->getJson("/api/v1/maintenances/{$this->maintenance->id}")->assertOk()->json('data');

        $this->assertCount(1, $json['photos']);
        $this->assertSame(1, $json['photos_count']);
        $this->assertArrayHasKey('variants', $json['photos'][0]);
    }

    public function test_timeline_has_no_photo_data_without_the_feature_and_keeps_it_with_the_feature(): void
    {
        $this->photoOf($this->maintenance, 'before');
        $this->photoOf($this->maintenance, 'after');

        $this->actingAsApiUser($this->owner);
        $event = collect($this->getJson("/api/v1/vehicles/{$this->vehicle->id}/timeline")->assertOk()->json('data.events'))
            ->firstWhere('id', $this->maintenance->id);

        $this->assertSame(0, $event['photos_count']);
        $this->assertFalse($event['photos_locked']);
        $this->assertSame([], $event['photo_preview']);

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($this->owner->fresh());
        $event = collect($this->getJson("/api/v1/vehicles/{$this->vehicle->id}/timeline")->assertOk()->json('data.events'))
            ->firstWhere('id', $this->maintenance->id);

        $this->assertSame(2, $event['photos_count']);
        $this->assertCount(2, $event['photo_preview']);
    }

    public function test_authorized_file_routes_deny_without_the_feature(): void
    {
        $photo = $this->photoOf($this->maintenance);

        $this->actingAsApiUser($this->owner);
        $this->getJson(route('api.maintenance-photos.show', $photo))->assertForbidden();

        $this->actingAs($this->owner)->get(route('maintenance-photos.show', $photo))->assertForbidden();

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($this->owner->fresh());
        $this->getJson(route('api.maintenance-photos.show', $photo))->assertOk();
        $this->actingAs($this->owner->fresh())->get(route('maintenance-photos.show', $photo))->assertOk();
    }

    public function test_no_signed_url_is_issued_to_a_viewer_without_the_feature(): void
    {
        $photo = $this->photoOf($this->maintenance);
        $photo->setRelation('maintenance', $this->maintenance);

        $this->assertNull(\App\Support\Maintenance\MaintenancePhotoUrls::forViewer($photo, $this->owner));

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->assertNotNull(\App\Support\Maintenance\MaintenancePhotoUrls::forViewer($photo, $this->owner->fresh()));
    }

    public function test_web_maintenance_detail_hides_the_photo_section_without_the_feature(): void
    {
        $this->photoOf($this->maintenance);

        $this->actingAs($this->owner)
            ->get(route('user.maintenances.show', $this->maintenance))
            ->assertOk()
            ->assertDontSee('data-slot="maintenance-photos"', false)
            ->assertDontSee('data-photo-gallery', false);

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAs($this->owner->fresh())
            ->get(route('user.maintenances.show', $this->maintenance))
            ->assertOk()
            ->assertSee('data-slot="maintenance-photos"', false);
    }

    public function test_workshop_form_hides_the_photo_step_and_rejects_uploads_without_the_feature(): void
    {
        $workshopUser = $this->workshopAccount();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshopUser->workshop->id,
            'user_id' => $workshopUser->id,
        ]);

        $createUrl = route('workshop.maintenances.create', ['license_plate' => $this->vehicle->license_plate]);

        $this->actingAs($workshopUser)->get($createUrl)
            ->assertOk()
            ->assertSee('id="secao-servico"', false)
            ->assertDontSee('id="secao-fotos"', false);
        $this->actingAs($workshopUser)->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertDontSee('id="secao-fotos"', false);

        $this->actingAs($workshopUser)->post(route('workshop.maintenances.store'), [
            'chassis' => self::OWNERLESS_CHASSIS,
            'new_vehicle' => ['brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021],
            'maintenance_type' => 'Revisão',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 50_000,
            'service_category' => 'mechanical',
            'photos' => ['vehicle_after' => [UploadedFile::fake()->image('carro.jpg')]],
        ])->assertForbidden();
        $this->assertSame(0, MaintenancePhoto::query()->count());

        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $this->actingAs($workshopUser->fresh())->get($createUrl)
            ->assertOk()
            ->assertSee('id="secao-fotos"', false);
        $this->actingAs($workshopUser->fresh())->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('id="secao-fotos"', false);
    }

    public function test_me_exposes_the_feature_state(): void
    {
        $this->actingAsApiUser($this->owner);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.features', ['maintenance_photos' => false]);

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($this->owner->fresh());
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.features', ['maintenance_photos' => true]);
    }

    public function test_me_reflects_the_workshop_flag_for_a_workshop_account(): void
    {
        $workshopUser = $this->workshopAccount();
        Feature::for($workshopUser->workshop)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($workshopUser->fresh());

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.features.maintenance_photos', true);
    }

    public function test_pdf_preference_in_the_api_needs_the_feature(): void
    {
        $this->actingAsApiUser($this->owner);
        $this->putJson('/api/v1/me', ['export_include_photos' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('export_include_photos');
        $this->assertNull($this->owner->fresh()->export_include_photos);

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAsApiUser($this->owner->fresh());
        $this->putJson('/api/v1/me', ['export_include_photos' => true])->assertOk();
        $this->assertTrue($this->owner->fresh()->export_include_photos);
    }

    public function test_pdf_preference_card_in_the_account_page_needs_the_feature(): void
    {
        $this->actingAs($this->owner)->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('Incluir fotos das manutenções');
        $this->actingAs($this->owner)->put(route('account.preferences'), ['export_include_photos' => '1'])->assertForbidden();
        $this->assertNull($this->owner->fresh()->export_include_photos);

        Feature::for($this->owner)->activate(MaintenancePhotos::class);

        $this->actingAs($this->owner->fresh())->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Incluir fotos das manutenções');
        $this->actingAs($this->owner->fresh())->put(route('account.preferences'), ['export_include_photos' => '1'])->assertRedirect();
        $this->assertTrue($this->owner->fresh()->export_include_photos);
    }

    public function test_public_search_keeps_showing_public_photos_to_a_visitor_without_the_feature(): void
    {
        $photo = $this->photoOf($this->maintenance, 'after');

        $json = $this->getJson('/api/v1/vehicles/search/'.$this->vehicle->license_plate)->assertOk()->json('data');

        $this->assertSame([$photo->id], collect($json['maintenances'][0]['photos'])->pluck('id')->all());
    }
}
