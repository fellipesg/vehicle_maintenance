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

    public function test_command_downloads_cover_for_vehicle_without_photo(): void
    {
        Storage::fake('public');

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
            'upload.wikimedia.org/*' => Http::response('fake-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Volvo',
            'model' => 'XC40 T4',
            'year' => 2021,
            'cover_photo_path' => null,
        ]);

        $this->artisan('vehicles:fetch-missing-covers')
            ->assertExitCode(0);

        $vehicle->refresh();

        $this->assertNotNull($vehicle->cover_photo_path);
        Storage::disk('public')->assertExists($vehicle->cover_photo_path);
        $this->assertStringStartsWith('vehicle-covers/', $vehicle->cover_photo_path);
    }

    public function test_command_skips_vehicles_with_existing_cover(): void
    {
        Storage::fake('public');
        Http::fake();

        Vehicle::factory()->create([
            'cover_photo_path' => 'vehicle-covers/existing.jpg',
        ]);

        $this->artisan('vehicles:fetch-missing-covers')
            ->expectsOutputToContain('Nenhum veículo pendente')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }
}
