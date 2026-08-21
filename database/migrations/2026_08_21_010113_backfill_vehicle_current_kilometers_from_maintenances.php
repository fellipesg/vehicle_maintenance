<?php

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $mileageService = app(VehicleMileageService::class);

        Vehicle::query()
            ->with('maintenances')
            ->orderBy('id')
            ->chunkById(100, function ($vehicles) use ($mileageService): void {
                foreach ($vehicles as $vehicle) {
                    $mileageService->refreshCurrentKilometers($vehicle);
                }
            });
    }

    public function down(): void
    {
        // Dados derivados — não revertemos o backfill.
    }
};
