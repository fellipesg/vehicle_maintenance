<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchMissingVehicleCoversCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_downloads_both_covers_for_vehicle_without_photo(): void
    {
        $this->fakeCoversDisk('r2');

        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'imageinfo' => [[
                                'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/demo.jpg/1280px-demo.jpg',
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a1/demo.jpg',
                            ]],
                        ],
                    ],
                ],
            ], 200),
            'upload.wikimedia.org/*' => Http::response($this->sampleJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Volvo',
            'model' => 'XC40 T4',
            'year' => 2021,
            'cover_photo_path' => null,
            'cover_photo_portrait_path' => null,
        ]);

        $this->artisan('vehicles:fetch-missing-covers')
            ->assertExitCode(0);

        $vehicle->refresh();

        $this->assertNotNull($vehicle->cover_photo_path);
        $this->assertNotNull($vehicle->cover_photo_portrait_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_portrait_path);
        $this->assertStringStartsWith('vehicle-covers/', $vehicle->cover_photo_path);
    }

    public function test_command_skips_vehicles_with_existing_cover(): void
    {
        $this->fakeCoversDisk('r2');
        Http::fake();

        Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/existing.jpg',
        ]);

        $this->artisan('vehicles:fetch-missing-covers')
            ->expectsOutputToContain('Nenhum veículo pendente')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_missing_orientation_fills_portrait_when_landscape_exists(): void
    {
        $this->fakeCoversDisk('r2');

        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a1/demo.jpg',
                            ]],
                        ],
                    ],
                ],
            ], 200),
            'upload.wikimedia.org/*' => Http::response($this->sampleJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        Storage::disk('r2')->put('vehicle-covers/existing-landscape.jpg', $this->sampleJpeg());

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Toyota',
            'model' => 'Corolla XEi',
            'year' => 2019,
            'cover_photo_path' => 'vehicle-covers/existing-landscape.jpg',
            'cover_photo_portrait_path' => null,
        ]);

        $this->artisan('vehicles:fetch-missing-covers', ['--missing-orientation' => true])
            ->assertExitCode(0);

        $vehicle->refresh();

        $this->assertSame('vehicle-covers/existing-landscape.jpg', $vehicle->cover_photo_path);
        $this->assertNotNull($vehicle->cover_photo_portrait_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_portrait_path);
    }

    private function sampleJpeg(): string
    {
        $image = imagecreatetruecolor(1280, 720);
        imagefilledrectangle($image, 0, 0, 1279, 719, imagecolorallocate($image, 40, 80, 120));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
