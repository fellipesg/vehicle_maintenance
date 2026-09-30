<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_unauthenticated_cannot_list_vehicles(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
    }

    public function test_web_session_can_list_my_vehicles_from_user_portal(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'XC4D3M0']);
        $this->attachVehicleToUser($user, $vehicle);

        $this->actingAs($user)
            ->withHeader('Referer', url('/usuario/veiculos'))
            ->getJson('/api/v1/my-vehicles')
            ->assertOk()
            ->assertJsonPath('data.0.license_plate', 'XC4D3M0')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_list_only_tenant_vehicles(): void
    {
        $user = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();

        $owned = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();

        $this->attachVehicleToUser($user, $owned);
        $this->attachVehicleToUser($otherUser, $other);

        $response = $this->getJson('/api/v1/vehicles');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_vehicle(): void
    {
        $this->actingAsApiUser();

        $vehicleData = [
            'license_plate' => 'ABC1234',
            'renavam' => '12345678901',
            'chassis' => '9BWZZZ377VT004251',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'color' => 'Branco',
            'current_kilometers' => 45000,
            'terms_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/vehicles', $vehicleData);

        $response->assertCreated()
            ->assertJsonPath('data.license_plate', 'ABC1234');

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC1234']);
    }

    public function test_cannot_create_vehicle_with_invalid_data(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['success', 'errors']);
    }

    public function test_cannot_create_vehicle_with_duplicate_license_plate(): void
    {
        $this->actingAsApiUser();
        Vehicle::factory()->create(['license_plate' => 'ABC1234']);

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1234',
            'renavam' => '98765432109',
            'chassis' => '9BWZZZ377VT004252',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
            'current_kilometers' => 12000,
            'terms_accepted' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['license_plate']);
    }

    public function test_can_show_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id);
    }

    public function test_show_returns_slim_payload_without_maintenances_list(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.maintenances_count', 3)
            ->assertJsonMissingPath('data.maintenances');
    }

    public function test_cannot_show_vehicle_from_other_tenant(): void
    {
        $user = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($otherUser, $vehicle);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertForbidden();
    }

    public function test_cannot_update_vehicle_from_other_tenant(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($otherUser, $vehicle);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'brand' => 'Hacked Brand',
        ])->assertForbidden();
    }

    public function test_cannot_delete_vehicle_from_other_tenant(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($otherUser, $vehicle);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertForbidden();
    }

    public function test_cannot_link_vehicle_owned_by_other_tenant(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($otherUser, $vehicle);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => $vehicle->license_plate,
            'renavam' => $vehicle->renavam,
        ])->assertForbidden();
    }

    public function test_can_link_unowned_vehicle_with_matching_document(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
        ]);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => 'abc-1d23',
            'renavam' => '123.456.789-01',
        ])->assertOk();

        $pivot = $user->vehicles()->where('vehicle_id', $vehicle->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->is_current_owner);
        $this->assertSame($user->tenant_id, $pivot->tenant_id);
        $this->assertNull($pivot->ownership_verified_at, 'Only a CRLV-e import may verify ownership.');
    }

    public function test_cannot_link_unowned_vehicle_without_the_document_fields(): void
    {
        $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['license_plate', 'renavam']);

        $this->assertDatabaseMissing('user_vehicles', ['vehicle_id' => $vehicle->id]);
    }

    public function test_cannot_link_unowned_vehicle_by_guessing_the_id(): void
    {
        $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
        ]);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => 'ABC1D23',
            'renavam' => '99999999999',
        ])->assertStatus(422);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => 'XYZ9K88',
            'renavam' => '12345678901',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('user_vehicles', ['vehicle_id' => $vehicle->id]);
    }

    public function test_current_owner_can_relink_without_resending_the_document(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link")->assertOk();
    }

    public function test_link_attempts_are_rate_limited(): void
    {
        $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
                'license_plate' => 'XYZ9K88',
                'renavam' => '99999999999',
            ])->assertStatus(422);
        }

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => 'XYZ9K88',
            'renavam' => '99999999999',
        ])->assertStatus(429);
    }

    public function test_cannot_show_nonexistent_vehicle(): void
    {
        $this->actingAsApiUser();

        $this->getJson('/api/v1/vehicles/999')->assertNotFound();
    }

    public function test_can_update_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->putJson("/api/v1/vehicles/{$vehicle->id}", [
            'brand' => 'Updated Brand',
            'model' => 'Updated Model',
        ])->assertOk()
            ->assertJsonPath('data.brand', 'Updated Brand');
    }

    public function test_can_delete_owned_vehicle(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_vehicles', [
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_can_search_vehicle_by_license_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'XYZ9876']);

        $this->getJson('/api/v1/vehicles/search/XYZ9876')
            ->assertOk()
            ->assertJsonPath('data.license_plate', 'XYZ9876');
    }

    public function test_can_search_vehicle_by_renavam(): void
    {
        $vehicle = Vehicle::factory()->create(['renavam' => '11122233344']);

        $this->getJson('/api/v1/vehicles/search/11122233344')
            ->assertOk()
            ->assertJsonPath('data.renavam', '11122233344');
    }

    public function test_search_returns_404_for_nonexistent_vehicle(): void
    {
        $this->getJson('/api/v1/vehicles/search/INVALID')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_public_vehicle_search_does_not_expose_private_pii(): void
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'owner-secret@example.com',
            'phone' => '11999998888',
        ]);
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'PII1234',
            'chassis' => 'SECRETCHASSIS123',
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_name' => 'Oficina Teste',
        ]);

        $response = $this->getJson('/api/v1/vehicles/search/PII1234');

        $response->assertOk()
            ->assertJsonPath('data.license_plate', 'PII1234')
            ->assertJsonPath('data.chassis', 'SECRETCHASSIS123')
            ->assertJsonPath('data.matched_by', 'current_plate')
            ->assertJsonMissingPath('data.owners')
            ->assertJsonMissingPath('data.maintenances.0.user')
            ->assertJsonMissingPath('data.maintenances.0.invoices');

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('owner-secret@example.com', $payload);
        $this->assertStringNotContainsString('11999998888', $payload);
    }

    public function test_can_get_vehicle_maintenances(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->count(3)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_export_vehicle_maintenance_pdf(): void
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
            ->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $exportId = $queued->json('data.export_id');

        $this->getJson("/api/v1/vehicle-pdf-exports/{$exportId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['data' => ['download_url', 'filename']]);
    }

    public function test_can_upload_vehicle_cover_photo(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $file = UploadedFile::fake()->image('cover.jpg');

        $response = $this->post("/api/v1/vehicles/{$vehicle->id}/cover", [
            'cover' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['cover_photo_url', 'cover_photo_portrait_url']]);

        $vehicle->refresh();
        $this->assertNotNull($vehicle->cover_photo_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_path);
    }

    public function test_other_user_cannot_upload_vehicle_cover_photo(): void
    {
        $this->fakeCoversDisk('r2');

        $owner = $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        Sanctum::actingAs($otherUser);

        $file = UploadedFile::fake()->image('cover.jpg');

        $this->post("/api/v1/vehicles/{$vehicle->id}/cover", [
            'cover' => $file,
        ])->assertForbidden();
    }

    public function test_vehicle_show_includes_cover_photo_url(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Storage::disk('r2')->put('vehicle-covers/test.jpg', 'fake-image');
        $vehicle->update(['cover_photo_path' => 'vehicle-covers/test.jpg']);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_photo_url', AppStorage::coversUrl('vehicle-covers/test.jpg'));
    }

    public function test_can_upload_portrait_cover_without_removing_landscape(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/landscape.jpg',
        ]);
        Storage::disk('r2')->put('vehicle-covers/landscape.jpg', 'landscape');
        $this->attachVehicleToUser($user, $vehicle);

        $file = UploadedFile::fake()->image('portrait.jpg', 450, 800);

        $this->post("/api/v1/vehicles/{$vehicle->id}/cover", [
            'cover_portrait' => $file,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['cover_photo_portrait_url']]);

        $vehicle->refresh();
        $this->assertSame('vehicle-covers/landscape.jpg', $vehicle->cover_photo_path);
        $this->assertNotNull($vehicle->cover_photo_portrait_path);
        Storage::disk('r2')->assertExists('vehicle-covers/landscape.jpg');
        Storage::disk('r2')->assertExists($vehicle->cover_photo_portrait_path);
    }

    public function test_can_upload_both_covers_in_one_request(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $landscape = UploadedFile::fake()->image('landscape.jpg', 800, 450);
        $portrait = UploadedFile::fake()->image('portrait.jpg', 450, 800);

        $this->post("/api/v1/vehicles/{$vehicle->id}/cover", [
            'cover' => $landscape,
            'cover_portrait' => $portrait,
        ])->assertOk();

        $vehicle->refresh();
        $this->assertNotNull($vehicle->cover_photo_path);
        $this->assertNotNull($vehicle->cover_photo_portrait_path);
    }

    public function test_vehicle_show_includes_portrait_cover_url(): void
    {
        $this->fakeCoversDisk('r2');

        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        Storage::disk('r2')->put('vehicle-covers/portrait.jpg', 'fake-image');
        $vehicle->update(['cover_photo_portrait_path' => 'vehicle-covers/portrait.jpg']);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_photo_portrait_url', AppStorage::coversUrl('vehicle-covers/portrait.jpg'));
    }
}
