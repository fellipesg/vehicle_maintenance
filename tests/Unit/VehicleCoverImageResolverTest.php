<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleCoverImageResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_curated_url_for_2019_corolla_demo(): void
    {
        Http::fake();

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Toyota',
            'model' => 'Corolla XEi',
            'year' => 2019,
        ]);

        $url = app(VehicleCoverImageResolver::class)->resolveDownloadUrl($vehicle);

        $this->assertStringContainsString('Toyota_Corolla%2C_GIMS_2019', (string) $url);
        Http::assertNothingSent();
    }

    public function test_skips_vintage_commons_results_for_modern_vehicle(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'index' => 1,
                            'title' => 'File:1966 Toyota Corolla (E10) 01.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/a1/vintage.jpg',
                            ]],
                        ],
                        '2' => [
                            'index' => 2,
                            'title' => 'File:2017 Honda Civic VTi-S sedan (2018-10-29) 01.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/b/b2/modern.jpg',
                            ]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Civic EXL',
            'year' => 2017,
        ]);

        $url = app(VehicleCoverImageResolver::class)->resolveDownloadUrl($vehicle);

        $this->assertSame('https://upload.wikimedia.org/wikipedia/commons/3/36/2017_Honda_Civic_VTi-S_sedan_%282018-10-29%29_01.jpg', $url);
    }

    public function test_rejects_c180_image_for_c200_vehicle(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '1' => [
                            'index' => 1,
                            'title' => 'File:Mercedes-Benz C 180 (W205) rear.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/x/x1/c180.jpg',
                            ]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Mercedes-Benz',
            'model' => 'C 200',
            'year' => 2019,
        ]);

        $url = app(VehicleCoverImageResolver::class)->resolveDownloadUrl($vehicle);

        $this->assertStringContainsString('Mercedes-Benz_C_200_%28W205%2C_2019%29', (string) $url);
    }
}
