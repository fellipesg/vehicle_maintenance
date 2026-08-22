<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VehicleCoverImageResolver
{
    /**
     * URLs curadas quando a busca genérica falha ou traz modelos antigos.
     *
     * @var array<string, string>
     */
    private const DIRECT_URLS = [
        'mercedes-benz|c 200|2019' => 'https://upload.wikimedia.org/wikipedia/commons/3/3c/Mercedes-Benz_C_200_%28W205%2C_2019%29_%2853989141488%29.jpg',
        'toyota|corolla xei|2019' => 'https://upload.wikimedia.org/wikipedia/commons/f/fb/Toyota_Corolla%2C_GIMS_2019%2C_Le_Grand-Saconnex_%28GIMS1340%29.jpg',
        'honda|civic exl|2017' => 'https://upload.wikimedia.org/wikipedia/commons/3/36/2017_Honda_Civic_VTi-S_sedan_%282018-10-29%29_01.jpg',
    ];

    /**
     * @var array<string, string>
     */
    private const SEARCH_OVERRIDES = [
        'mercedes-benz|c 200|2019' => '2019 Mercedes-Benz C 200 W205',
        'mercedes-benz|gla 200|2014' => '2014 Mercedes-Benz GLA X156',
        'chery|tiggo 8 pro|2022' => '2022 Chery Tiggo 8',
        'volvo|xc40 t4|2021' => '2021 Volvo XC40',
        'jeep|compass|2018' => '2018 Jeep Compass MP',
        'toyota|corolla xei|2019' => '2019 Toyota Corolla E210 sedan',
        'honda|civic exl|2017' => '2017 Honda Civic FC sedan',
    ];

    public function resolveDownloadUrl(Vehicle $vehicle): ?string
    {
        $directUrl = self::DIRECT_URLS[$this->vehicleKey($vehicle)] ?? null;

        if (is_string($directUrl) && $directUrl !== '') {
            return $directUrl;
        }

        $searchTerm = $this->searchTerm($vehicle);
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'VehicleMaintenanceBot/1.0 (cover seeding)'])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'generator' => 'search',
                'gsrsearch' => $searchTerm,
                'gsrnamespace' => 6,
                'gsrlimit' => 12,
                'prop' => 'imageinfo',
                'iiprop' => 'url',
                'iiurlwidth' => 1280,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $pages = $response->json('query.pages', []);

        uasort($pages, fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        foreach ($pages as $page) {
            $title = (string) ($page['title'] ?? '');

            if (! $this->isRelevantImageTitle($title, $vehicle)) {
                continue;
            }

            if (! $this->matchesVehicleModel($title, $vehicle)) {
                continue;
            }

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
        $override = self::SEARCH_OVERRIDES[$this->vehicleKey($vehicle)] ?? null;

        if ($override !== null) {
            return $override;
        }

        return trim("{$vehicle->year} {$vehicle->brand} {$vehicle->model} car");
    }

    private function vehicleKey(Vehicle $vehicle): string
    {
        return Str::lower(trim($vehicle->brand))
            .'|'.Str::lower(trim($vehicle->model))
            .'|'.(int) $vehicle->year;
    }

    private function isRelevantImageTitle(string $title, Vehicle $vehicle): bool
    {
        $vehicleYear = (int) $vehicle->year;

        if ($vehicleYear <= 0) {
            return true;
        }

        if (! preg_match_all('/\b(19\d{2}|20\d{2})\b/', $title, $matches)) {
            return true;
        }

        foreach ($matches[1] as $yearMatch) {
            $year = (int) $yearMatch;

            if ($year < $vehicleYear - 2) {
                return false;
            }
        }

        return true;
    }

    private function matchesVehicleModel(string $title, Vehicle $vehicle): bool
    {
        $titleLower = Str::lower($title);
        $modelLower = Str::lower(trim($vehicle->model));

        if (str_contains($modelLower, 'c 200') && preg_match('/c[\s_\-]?180\b/', $titleLower)) {
            return false;
        }

        if (str_contains($modelLower, 'c 180') && preg_match('/c[\s_\-]?200\b/', $titleLower)) {
            return false;
        }

        if (str_contains($modelLower, 'gla 200') && str_contains($titleLower, 'gla 45')) {
            return false;
        }

        return true;
    }
}
