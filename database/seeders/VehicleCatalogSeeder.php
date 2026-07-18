<?php

namespace Database\Seeders;

use App\Models\VehicleBrand;
use App\Services\VehicleCatalogService;
use Illuminate\Database\Seeder;

class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (VehicleBrand::query()->exists()) {
            return;
        }

        $catalog = require database_path('data/vehicle_catalog.php');

        foreach ($catalog as $brandName => $models) {
            $brand = VehicleBrand::create([
                'name' => $brandName,
                'is_active' => true,
            ]);

            foreach ($models as $modelName) {
                $brand->models()->create([
                    'name' => $modelName,
                    'is_active' => true,
                ]);
            }
        }

        VehicleCatalogService::clearCache();
    }
}
