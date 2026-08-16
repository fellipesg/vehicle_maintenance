<?php

namespace Database\Seeders;

use App\Models\VehicleBrand;
use App\Services\VehicleCatalogService;
use Illuminate\Database\Seeder;

class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = require database_path('data/vehicle_catalog.php');

        foreach ($catalog as $brandName => $models) {
            $brand = VehicleBrand::query()->firstOrCreate(
                ['name' => $brandName],
                ['is_active' => true],
            );

            if (! $brand->is_active) {
                $brand->update(['is_active' => true]);
            }

            foreach ($models as $modelName) {
                $brand->models()->firstOrCreate(
                    ['name' => $modelName],
                    ['is_active' => true],
                );
            }
        }

        VehicleCatalogService::clearCache();
    }
}
