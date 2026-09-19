<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AddressGeocoder
{
    private const USER_AGENT = 'Revisalog/1.0 (contact: admin@revisalog.com.br)';

    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $addressLine, ?string $countryCode = 'br'): ?array
    {
        $normalized = $this->normalizeAddress($addressLine);
        if ($normalized === '') {
            return null;
        }

        $cacheKey = 'geo:'.sha1($normalized.($countryCode ?? ''));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        $query = [
            'q' => $normalized,
            'format' => 'json',
            'limit' => 1,
        ];

        if ($countryCode !== null && $countryCode !== '') {
            $query['countrycodes'] = strtolower($countryCode);
        }

        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->get(self::NOMINATIM_URL, $query);

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json();
        if (! is_array($results) || $results === []) {
            return null;
        }

        $first = $results[0];
        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return null;
        }

        $coords = [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];

        Cache::forever($cacheKey, $coords);

        return $coords;
    }

    public function normalizeAddress(string $address): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($address)) ?? '';

        return $collapsed;
    }

    public function buildBrazilAddress(
        ?string $street,
        ?string $number,
        ?string $city,
        ?string $state,
        ?string $postalCode = null,
    ): string {
        $parts = array_filter([
            $street,
            $number,
            $city,
            $state,
            $postalCode,
            'Brasil',
        ], fn (?string $part) => $part !== null && trim($part) !== '');

        return implode(', ', $parts);
    }
}
