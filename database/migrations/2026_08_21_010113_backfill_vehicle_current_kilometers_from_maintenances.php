<?php

use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill histórico: `vehicles.current_kilometers` a partir das OS que contam.
 *
 * A conta está escrita aqui, à mão, e não vem de VehicleMileageService de propósito. Ela era a
 * fórmula do serviço em agosto de 2026; hoje o recálculo lê `vehicle_odometer_readings`, uma tabela
 * que só existe a partir de uma migration posterior a esta. Migration que chama serviço de
 * aplicação quebra no dia em que o serviço muda — e aqui quebraria ao reaplicar o histórico sobre um
 * banco com veículos. Dado derivado de migration fica congelado no tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Vehicle::query()
            ->orderBy('id')
            ->chunkById(100, function ($vehicles): void {
                foreach ($vehicles as $vehicle) {
                    $highestMaintenance = DB::table('maintenances')
                        ->where('vehicle_id', $vehicle->id)
                        ->whereNull('owner_disputed_at')
                        ->whereNull('hidden_by_user_id')
                        ->max('kilometers');

                    $vehicle->forceFill([
                        'current_kilometers' => max(
                            (int) ($vehicle->odometer_at_registration ?? 0),
                            (int) $highestMaintenance,
                        ),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Dados derivados — não revertemos o backfill.
    }
};
