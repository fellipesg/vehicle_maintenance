<?php

namespace App\Support\Maintenance;

use App\Support\Vehicle\VehicleMaintenanceHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Filtros da toolbar de <x-maintenance.list> lidos da query string, para o controller aplicar na
 * consulta e a view mostrar o estado:
 *
 * - verified: '1' (Selo da oficina), '0' (Declaradas) ou '' (Todas).
 * - veiculo: id do veículo, ou null.
 *
 * Ex.: $filters = MaintenanceListFilters::fromRequest($request);
 *      $maintenances = $filters->apply($user->maintenancesQuery())->paginate(15)->withQueryString();
 */
final class MaintenanceListFilters
{
    public const VERIFIED_PARAM = 'verified';

    public const VEHICLE_PARAM = 'veiculo';

    public function __construct(
        public readonly string $verified = VehicleMaintenanceHistory::FILTER_ALL,
        public readonly ?int $vehicleId = null,
    ) {}

    public static function fromRequest(Request $request, string $verifiedParam = self::VERIFIED_PARAM, string $vehicleParam = self::VEHICLE_PARAM): self
    {
        $vehicle = $request->query($vehicleParam);

        return new self(
            VehicleMaintenanceHistory::normalizeFilter($request->query($verifiedParam)),
            is_scalar($vehicle) && ctype_digit((string) $vehicle) && (int) $vehicle > 0 ? (int) $vehicle : null,
        );
    }

    /**
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->when($this->verified === VehicleMaintenanceHistory::FILTER_SEALED, fn (Builder|Relation $builder) => $builder->whereNotNull('verified_at'))
            ->when($this->verified === VehicleMaintenanceHistory::FILTER_DECLARED, fn (Builder|Relation $builder) => $builder->whereNull('verified_at'))
            ->when($this->vehicleId !== null, fn (Builder|Relation $builder) => $builder->where('vehicle_id', $this->vehicleId));
    }

    public function isFiltered(): bool
    {
        return $this->verified !== VehicleMaintenanceHistory::FILTER_ALL || $this->vehicleId !== null;
    }
}
