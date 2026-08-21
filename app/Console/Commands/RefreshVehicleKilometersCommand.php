<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Console\Command;

class RefreshVehicleKilometersCommand extends Command
{
    protected $signature = 'vehicles:refresh-kilometers';

    protected $description = 'Atualiza a quilometragem atual de todos os veículos com base na última manutenção registrada';

    public function handle(VehicleMileageService $mileageService): int
    {
        $updated = 0;

        Vehicle::query()
            ->with('maintenances')
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
