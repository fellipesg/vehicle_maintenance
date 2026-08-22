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

    public function test_store_uploads_to_covers_disk(): void
    {
        $this->fakeCoversDisk('r2');

        $vehicle = Vehicle::factory()->create();
        $file = UploadedFile::fake()->image('cover.jpg');

        $updated = app(VehicleCoverService::class)->store($vehicle, $file);

        $this->assertNotNull($updated->cover_photo_path);
        Storage::disk('r2')->assertExists($updated->cover_photo_path);
        $this->assertSame('r2', AppStorage::coversDiskName());
    }

    public function test_store_replaces_previous_cover_on_covers_disk(): void
    {
        $this->fakeCoversDisk('r2');

        Storage::disk('r2')->put('vehicle-covers/old.jpg', 'old');

        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/old.jpg',
        ]);

        $file = UploadedFile::fake()->image('new-cover.jpg');
        $updated = app(VehicleCoverService::class)->store($vehicle, $file);

        Storage::disk('r2')->assertMissing('vehicle-covers/old.jpg');
        Storage::disk('r2')->assertExists($updated->cover_photo_path);
    }
}
