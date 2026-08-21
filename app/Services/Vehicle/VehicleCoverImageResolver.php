<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VehicleCoverImageResolver
{
    /**
     * @var array<string, string>
     */
    private const SEARCH_OVERRIDES = [
        'mercedes-benz|c 200' => 'Mercedes-Benz C-Class W205 2019',
        'mercedes-benz|gla 200' => 'Mercedes-Benz GLA X156',
        'chery|tiggo 8 pro' => 'Chery Tiggo 8',
        'volvo|xc40 t4' => 'Volvo XC40',
        'jeep|compass' => 'Jeep Compass MP',
    ];

    public function resolveDownloadUrl(Vehicle $vehicle): ?string
    {
        $searchTerm = $this->searchTerm($vehicle);
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'VehicleMaintenanceBot/1.0 (cover seeding)'])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'generator' => 'search',
                'gsrsearch' => $searchTerm,
                'gsrnamespace' => 6,
                'gsrlimit' => 8,
                'prop' => 'imageinfo',
                'iiprop' => 'url',
                'iiurlwidth' => 1280,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $pages = $response->json('query.pages', []);

        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;

            if ($info === null) {
                continue;
            }

            $url = $info['url'] ?? $info['thumburl'] ?? null;

            if (is_string($url) && $url !== '') {
                return Str::before($url, '?');
            }
        }

        return null;
    }

    private function searchTerm(Vehicle $vehicle): string
    {
        $key = Str::lower(trim($vehicle->brand)).'|'.Str::lower(trim($vehicle->model));

        $override = self::SEARCH_OVERRIDES[$key] ?? null;

        if ($override !== null) {
            return $override.' car';
        }

        return trim("{$vehicle->brand} {$vehicle->model} {$vehicle->year} car");
    }
}
