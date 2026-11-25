<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Console\Command;

/**
 * Reescreve `vehicles.current_kilometers` de todos os veículos a partir de
 * `vehicle_odometer_readings` (VehicleMileageService::refreshCurrentKilometers).
 *
 * É o conserto da coluna velha — registro gravado antes de o recálculo existir, por exemplo.
 * Veículo que a projeção ainda não alcançou ele deixa como está (o recálculo se cala quando não há
 * leitura), então ele não estraga nada; mas também não conserta esse veículo: quem alcança o
 * histórico é o `odometer:backfill-readings`, e ele vem primeiro (.ai/rules/models.md).
 */
class RefreshVehicleKilometersCommand extends Command
{
    protected $signature = 'vehicles:refresh-kilometers';

    protected $description = 'Recalcula a quilometragem atual de todos os veículos a partir das leituras do odômetro';

    public function handle(VehicleMileageService $mileageService): int
    {
        $updated = 0;

        Vehicle::query()
            ->orderBy('id')
            ->chunkById(100, function ($vehicles) use ($mileageService, &$updated): void {
                foreach ($vehicles as $vehicle) {
                    $before = $vehicle->current_kilometers;

                    $mileageService->refreshCurrentKilometers($vehicle);
                    $vehicle->refresh();

                    if ($vehicle->current_kilometers !== $before) {
                        $updated++;
                        $this->line(sprintf(
                            'Veículo #%d (%s): %s → %s km',
                            $vehicle->id,
                            $vehicle->license_plate,
                            $before === null ? '—' : number_format((int) $before, 0, ',', '.'),
                            $vehicle->current_kilometers === null
                                ? '—'
                                : number_format((int) $vehicle->current_kilometers, 0, ',', '.'),
                        ));
                    }
                }
            });

        $this->info("Concluído. {$updated} veículo(s) atualizado(s).");

        return self::SUCCESS;
    }
}
