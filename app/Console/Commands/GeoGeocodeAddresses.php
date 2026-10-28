<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workshop;
use App\Services\Geo\AddressGeocoder;
use Illuminate\Console\Command;

class GeoGeocodeAddresses extends Command
{
    protected $signature = 'geo:geocode-addresses
                            {--sleep=1 : Seconds to wait between Nominatim requests}';

    protected $description = 'Geocode users and workshops that have address data but missing coordinates';

    public function handle(AddressGeocoder $geocoder): int
    {
        $sleepSeconds = max(0, (int) $this->option('sleep'));

        $workshopCount = $this->geocodeWorkshops($geocoder, $sleepSeconds);
        $userCount = $this->geocodeUsers($geocoder, $sleepSeconds);

        $this->info("Geocoded {$workshopCount} workshop(s) and {$userCount} user(s).");

        return self::SUCCESS;
    }

    private function geocodeWorkshops(AddressGeocoder $geocoder, int $sleepSeconds): int
    {
        $count = 0;

        Workshop::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotNull('street')
            ->where('street', '!=', '')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->orderBy('id')
            ->chunkById(50, function ($workshops) use ($geocoder, $sleepSeconds, &$count): void {
                foreach ($workshops as $workshop) {
                    if ($workshop->latitude !== null || $workshop->longitude !== null) {
                        continue;
                    }

                    $address = $geocoder->buildBrazilAddress(
                        $workshop->street,
                        $workshop->number,
                        $workshop->city,
                        $workshop->state,
                        $workshop->cep,
                    );

                    $coords = $geocoder->geocode($address);
                    if ($coords !== null) {
                        $workshop->update([
                            'latitude' => $coords['lat'],
                            'longitude' => $coords['lng'],
                        ]);
                        $count++;
                    }

                    if ($sleepSeconds > 0) {
                        sleep($sleepSeconds);
                    }
                }
            });

        return $count;
    }

    /**
     * Só o proprietário. Loja e oficina têm endereço no cadastro da empresa, e o que
     * sobrou nas colunas de `users` dessas contas não aparece em mapa nenhum
     * (admin.maps.users filtra user_type = 'user'): geocodificar é gastar chamada
     * do Nominatim à toa.
     */
    private function geocodeUsers(AddressGeocoder $geocoder, int $sleepSeconds): int
    {
        $count = 0;

        User::query()
            ->where('user_type', 'user')
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($geocoder, $sleepSeconds, &$count): void {
                foreach ($users as $user) {
                    if ($user->latitude !== null || $user->longitude !== null) {
                        continue;
                    }

                    $address = $geocoder->buildBrazilAddress(
                        $user->street,
                        $user->number,
                        $user->city,
                        $user->state,
                        $user->postal_code,
                    );

                    if (trim(str_replace(',', '', $address)) === 'Brasil') {
                        continue;
                    }

                    $coords = $geocoder->geocode($address);
                    if ($coords !== null) {
                        $user->update([
                            'latitude' => $coords['lat'],
                            'longitude' => $coords['lng'],
                        ]);
                        $count++;
                    }

                    if ($sleepSeconds > 0) {
                        sleep($sleepSeconds);
                    }
                }
            });

        return $count;
    }
}
