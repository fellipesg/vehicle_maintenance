<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverService;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleCoverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_landscape_uploads_to_covers_disk(): void
    {
        $this->fakeCoversDisk('r2');

        $vehicle = Vehicle::factory()->create();
        $file = UploadedFile::fake()->image('cover.jpg');

        $updated = app(VehicleCoverService::class)->storeLandscape($vehicle, $file);

        $this->assertNotNull($updated->cover_photo_path);
        Storage::disk('r2')->assertExists($updated->cover_photo_path);
        $this->assertSame('r2', AppStorage::coversDiskName());
    }

    public function test_store_portrait_uploads_to_covers_disk(): void
    {
        $this->fakeCoversDisk('r2');

        $vehicle = Vehicle::factory()->create();
        $file = UploadedFile::fake()->image('portrait.jpg', 450, 800);

        $updated = app(VehicleCoverService::class)->storePortrait($vehicle, $file);

        $this->assertNotNull($updated->cover_photo_portrait_path);
        Storage::disk('r2')->assertExists($updated->cover_photo_portrait_path);
    }

    public function test_store_landscape_replaces_previous_landscape_only(): void
    {
        $this->fakeCoversDisk('r2');

        Storage::disk('r2')->put('vehicle-covers/old-landscape.jpg', 'old');
        Storage::disk('r2')->put('vehicle-covers/portrait.jpg', 'portrait');

        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/old-landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/portrait.jpg',
        ]);

        $file = UploadedFile::fake()->image('new-cover.jpg');
        $updated = app(VehicleCoverService::class)->storeLandscape($vehicle, $file);

        Storage::disk('r2')->assertMissing('vehicle-covers/old-landscape.jpg');
        Storage::disk('r2')->assertExists('vehicle-covers/portrait.jpg');
        Storage::disk('r2')->assertExists($updated->cover_photo_path);
        $this->assertSame('vehicle-covers/portrait.jpg', $updated->cover_photo_portrait_path);
    }

    public function test_store_portrait_replaces_previous_portrait_only(): void
    {
        $this->fakeCoversDisk('r2');

        Storage::disk('r2')->put('vehicle-covers/landscape.jpg', 'landscape');
        Storage::disk('r2')->put('vehicle-covers/old-portrait.jpg', 'old');

        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/old-portrait.jpg',
        ]);

        $file = UploadedFile::fake()->image('new-portrait.jpg', 450, 800);
        $updated = app(VehicleCoverService::class)->storePortrait($vehicle, $file);

        Storage::disk('r2')->assertExists('vehicle-covers/landscape.jpg');
        Storage::disk('r2')->assertMissing('vehicle-covers/old-portrait.jpg');
        Storage::disk('r2')->assertExists($updated->cover_photo_portrait_path);
        $this->assertSame('vehicle-covers/landscape.jpg', $updated->cover_photo_path);
    }
}
