<?php

namespace Tests\Unit;

use App\Services\Geo\AddressGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressGeocoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocode_returns_coordinates_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-23.550520', 'lon' => '-46.633308'],
            ], 200),
        ]);

        $geocoder = app(AddressGeocoder::class);
        $result = $geocoder->geocode('Av. Paulista, 1000, São Paulo, SP, Brasil');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(-23.55052, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-46.633308, $result['lng'], 0.0001);
    }

    public function test_geocode_returns_null_when_api_fails(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 500),
        ]);

        $geocoder = app(AddressGeocoder::class);
        $this->assertNull($geocoder->geocode('Endereço inválido xyz'));
    }
}
