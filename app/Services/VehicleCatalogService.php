<?php

namespace App\Services;

use App\Models\VehicleBrand;
use Illuminate\Support\Facades\Cache;

class VehicleCatalogService
{
    private const CACHE_KEY = 'vehicle_catalog';

    /**
     * @return array<string, string[]>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $catalog = [];

            VehicleBrand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->with(['models' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name')])
                ->get()
                ->each(function (VehicleBrand $brand) use (&$catalog): void {
                    $catalog[$brand->name] = $brand->models
                        ->pluck('name')
                        ->all();
                });

            return $catalog;
        });
    }

    /**
     * @return string[]
     */
    public function brands(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return string[]
     */
    public function models(string $brand): array
    {
        return $this->all()[$brand] ?? [];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
