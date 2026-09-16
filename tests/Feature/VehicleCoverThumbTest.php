<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverCropper;
use App\Services\Vehicle\VehicleCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleCoverThumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_landscape_generates_192_square_thumb(): void
    {
        $this->fakeCoversDisk('r2');

        $vehicle = Vehicle::factory()->create();
        $file = UploadedFile::fake()->image('cover.jpg', 800, 600);

        $updated = app(VehicleCoverService::class)->storeLandscape($vehicle, $file);

        $this->assertNotNull($updated->cover_photo_thumb_path);
        Storage::disk('r2')->assertExists($updated->cover_photo_thumb_path);

        $bytes = Storage::disk('r2')->get($updated->cover_photo_thumb_path);
        $image = imagecreatefromstring($bytes);
        $this->assertSame(192, imagesx($image));
        $this->assertSame(192, imagesy($image));
        imagedestroy($image);
    }

    public function test_thumb_url_falls_back_to_portrait_or_landscape_when_path_missing(): void
    {
        $this->configurePublicCoversDisk();

        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/landscape.jpg',
            'cover_photo_portrait_path' => null,
            'cover_photo_thumb_path' => null,
        ]);

        $this->assertStringContainsString('landscape.jpg', (string) $vehicle->cover_photo_thumb_url);
    }

    public function test_generate_command_only_processes_missing_thumbs(): void
    {
        $this->fakeCoversDisk('r2');

        $withThumb = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/existing.jpg',
            'cover_photo_thumb_path' => 'vehicle-covers/existing-thumb.jpg',
        ]);
        Storage::disk('r2')->put('vehicle-covers/existing.jpg', $this->sampleJpegBytes(400, 300));

        $missing = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/missing-thumb.jpg',
            'cover_photo_thumb_path' => null,
        ]);
        Storage::disk('r2')->put('vehicle-covers/missing-thumb.jpg', $this->sampleJpegBytes(400, 300));

        Artisan::call('vehicles:generate-cover-thumbs', ['--missing' => true]);

        $withThumb->refresh();
        $missing->refresh();

        $this->assertSame('vehicle-covers/existing-thumb.jpg', $withThumb->cover_photo_thumb_path);
        $this->assertNotNull($missing->cover_photo_thumb_path);
        Storage::disk('r2')->assertExists($missing->cover_photo_thumb_path);
    }

    public function test_replacing_landscape_removes_previous_landscape_and_thumb_files(): void
    {
        $this->fakeCoversDisk('r2');

        Storage::disk('r2')->put('vehicle-covers/old-landscape.jpg', 'old');
        Storage::disk('r2')->put('vehicle-covers/old-thumb.jpg', 'old-thumb');
        Storage::disk('r2')->put('vehicle-covers/portrait.jpg', 'portrait');

        $vehicle = Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/old-landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/portrait.jpg',
            'cover_photo_thumb_path' => 'vehicle-covers/old-thumb.jpg',
        ]);

        $file = UploadedFile::fake()->image('new-cover.jpg', 640, 480);
        app(VehicleCoverService::class)->storeLandscape($vehicle, $file);

        Storage::disk('r2')->assertMissing('vehicle-covers/old-landscape.jpg');
        Storage::disk('r2')->assertMissing('vehicle-covers/old-thumb.jpg');
        Storage::disk('r2')->assertExists('vehicle-covers/portrait.jpg');
    }

    public function test_crop_to_thumb_outputs_192_jpeg(): void
    {
        $bytes = app(VehicleCoverCropper::class)->cropToThumb($this->sampleJpegBytes(900, 500));
        $image = imagecreatefromstring($bytes);
        $this->assertSame(192, imagesx($image));
        $this->assertSame(192, imagesy($image));
        imagedestroy($image);
    }

    private function sampleJpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        return $jpeg;
    }
}
