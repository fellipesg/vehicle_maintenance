<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenancePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_can_upload_before_and_after_vehicle_and_part_photos(): void
    {
        Storage::fake('public');

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshop->id]);

        $this->actingAsApiUser($workshopUser);

        foreach ([
            ['vehicle', 'before'],
            ['vehicle', 'after'],
            ['part', 'before'],
            ['part', 'after'],
        ] as [$subject, $stage]) {
            $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", [
                'photo' => UploadedFile::fake()->image("{$subject}-{$stage}.jpg"),
                'subject' => $subject,
                'stage' => $stage,
            ])->assertCreated();
        }

        $this->assertSame(4, MaintenancePhoto::where('maintenance_id', $maintenance->id)->count());
    }

    public function test_photo_limit_per_group_is_enforced(): void
    {
        Storage::fake('public');

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshop->id]);

        $this->actingAsApiUser($workshopUser);

        for ($i = 0; $i < MaintenancePhoto::MAX_PER_GROUP; $i++) {
            $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", [
                'photo' => UploadedFile::fake()->image("v{$i}.jpg"),
                'subject' => 'vehicle',
                'stage' => 'before',
            ])->assertCreated();
        }

        $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", [
            'photo' => UploadedFile::fake()->image('extra.jpg'),
            'subject' => 'vehicle',
            'stage' => 'before',
        ])->assertStatus(422);
    }

    public function test_other_workshop_cannot_upload_photos(): void
    {
        Storage::fake('public');

        $maintenance = Maintenance::factory()->create(['workshop_id' => Workshop::factory()->create()->id]);
        $otherWorkshopUser = User::factory()->asWorkshop()->create();

        $this->actingAsApiUser($otherWorkshopUser);

        $this->postJson("/api/v1/maintenances/{$maintenance->id}/photos", [
            'photo' => UploadedFile::fake()->image('blocked.jpg'),
            'subject' => 'vehicle',
            'stage' => 'after',
        ])->assertForbidden();
    }

    public function test_public_vehicle_search_hides_before_photos(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'PUB1234']);
        $maintenance = Maintenance::factory()->create(['vehicle_id' => $vehicle->id]);

        MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_BEFORE,
            'path' => 'maintenance-photos/before.jpg',
        ]);

        MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => MaintenancePhoto::SUBJECT_VEHICLE,
            'stage' => MaintenancePhoto::STAGE_AFTER,
            'path' => 'maintenance-photos/after.jpg',
        ]);

        $response = $this->getJson('/api/v1/vehicles/search/PUB1234')
            ->assertOk();

        $photos = $response->json('data.maintenances.0.photos');
        $this->assertCount(1, $photos);
    }
}
