<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoGeocodeAddressesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function addressAttributes(): array
    {
        return [
            'postal_code' => '79010200',
            'street' => 'Rua Treze de Junho',
            'number' => '3000',
            'city' => 'Campo Grande',
            'state' => 'MS',
            'country' => 'Brasil',
            'latitude' => null,
            'longitude' => null,
        ];
    }

    private function fakeNominatim(): void
    {
        Cache::flush();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '-20.4697', 'lon' => '-54.6201'],
            ]),
        ]);
    }

    public function test_it_geocodes_owner_accounts(): void
    {
        $this->fakeNominatim();

        $owner = User::factory()->asUser()->create($this->addressAttributes());

        $this->artisan('geo:geocode-addresses', ['--sleep' => 0])
            ->assertExitCode(0);

        $owner->refresh();

        $this->assertNotNull($owner->latitude);
        $this->assertNotNull($owner->longitude);
    }

    public function test_it_leaves_garage_and_workshop_accounts_alone(): void
    {
        $this->fakeNominatim();

        $garage = User::factory()->asGarage()->create($this->addressAttributes());
        $workshop = User::factory()->asWorkshop()->create($this->addressAttributes());

        $this->artisan('geo:geocode-addresses', ['--sleep' => 0])
            ->expectsOutputToContain('0 user(s)')
            ->assertExitCode(0);

        $garage->refresh();
        $workshop->refresh();

        $this->assertNull($garage->latitude);
        $this->assertNull($garage->longitude);
        $this->assertNull($workshop->latitude);
        $this->assertNull($workshop->longitude);
    }
}
