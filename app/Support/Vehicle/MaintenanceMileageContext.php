<?php

namespace App\Support\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Dados para o formulário de manutenção calcular no navegador a mesma faixa de quilometragem que o
 * App\Services\Vehicle\VehicleMileageService valida no servidor (resources/js/utils/
 * maintenance-kilometers.js): o hodômetro do cadastro, a data do cadastro e a quilometragem de cada
 * registro do veículo. Usado pelo Proprietário e pelo Lojista (data-mileage no formulário).
 *
 * Carregue antes as manutenções dos veículos só com as colunas usadas, para não consultar por
 * veículo: ->with(['maintenances' => fn ($query) => $query->select(MaintenanceMileageContext::COLUMNS)]).
 *
 * Ex.: 'mileage' => MaintenanceMileageContext::for($vehicles)
 *      'mileage' => MaintenanceMileageContext::for(collect([$vehicle]), except: $maintenance)
 */
final class MaintenanceMileageContext
{
    /**
     * Colunas de maintenances que o contexto lê.
     *
     * @var list<string>
     */
    public const COLUMNS = ['id', 'vehicle_id', 'kilometers', 'maintenance_date'];

    /**
     * @param  Collection<int, Vehicle>  $vehicles  com a relação maintenances carregada
     * @param  Maintenance|null  $except  manutenção em edição, que fica fora da faixa
     * @return list<array{id: int, current_kilometers: int|null, registration_kilometers: int|null, registered_on: string|null, records: list<array{date: string, kilometers: int}>}>
     */
    public static function for(Collection $vehicles, ?Maintenance $except = null): array
    {
        return $vehicles->map(fn (Vehicle $vehicle): array => [
            'id' => $vehicle->id,
            'current_kilometers' => $vehicle->current_kilometers,
            'registration_kilometers' => $vehicle->odometer_at_registration ?? $vehicle->current_kilometers,
            'registered_on' => $vehicle->created_at?->toDateString(),
            'records' => $vehicle->maintenances
                ->filter(fn (Maintenance $record): bool => $record->kilometers !== null
                    && $record->maintenance_date !== null
                    && ($except === null || $record->id !== $except->id))
                ->map(fn (Maintenance $record): array => [
                    'date' => $record->maintenance_date->toDateString(),
                    'kilometers' => (int) $record->kilometers,
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }
}
