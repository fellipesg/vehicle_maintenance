<?php

namespace App\Support\Maintenance;

use App\Enums\ServiceCategory;
use App\Models\MaintenancePhoto;
use App\Support\Vehicle\VehicleMaintenanceHistory;
use App\Support\VehiclePlateSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Filtros da lista "Ordens de serviço" da oficina, lidos da query string (GET) para o controller
 * aplicar na consulta e a view mostrar o estado:
 *
 * - placa: parte da placa, sem máscara ("ABC1" acha "ABC1D23");
 * - de / ate: período da data do serviço (Y-m-d);
 * - categoria: App\Enums\ServiceCategory;
 * - verified: '1' (Selo da oficina), '0' (declaradas por clientes que citaram a oficina) ou '';
 * - anexos: com-nfe | sem-nfe | sem-fotos-depois (as duas últimas são pendências do Início);
 * - garantia: vigente | vencendo (vence nos próximos 30 dias);
 * - ordenar / direcao: data (o padrão, mais recente primeiro) ou km, asc | desc.
 *
 * Ex.: $filters = WorkshopMaintenanceFilters::fromRequest($request);
 *      $filters->apply($workshop->maintenances())->paginate(15)->withQueryString();
 */
final class WorkshopMaintenanceFilters
{
    public const EXPIRING_WITHIN_DAYS = 30;

    /**
     * @var array<string, string>
     */
    public const ATTACHMENT_OPTIONS = [
        'com-nfe' => 'Com NF-e',
        'sem-nfe' => 'Sem NF-e',
        'sem-fotos-depois' => 'Sem fotos de depois',
    ];

    /**
     * @var array<string, string>
     */
    public const WARRANTY_OPTIONS = [
        'vigente' => 'Garantia vigente',
        'vencendo' => 'Vence em 30 dias',
    ];

    /**
     * @var array<string, string>
     */
    public const SORT_COLUMNS = [
        'data' => 'maintenance_date',
        'km' => 'kilometers',
    ];

    public function __construct(
        public readonly string $plate = '',
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $until = null,
        public readonly ?string $category = null,
        public readonly string $verified = VehicleMaintenanceHistory::FILTER_ALL,
        public readonly ?string $attachments = null,
        public readonly ?string $warranty = null,
        public readonly string $sort = 'data',
        public readonly string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $category = self::scalar($request->query('categoria'));
        $attachments = self::scalar($request->query('anexos'));
        $warranty = self::scalar($request->query('garantia'));
        $sort = self::scalar($request->query('ordenar'));
        $direction = self::scalar($request->query('direcao'));
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'data';

        return new self(
            plate: substr(VehiclePlateSearch::normalize(self::scalar($request->query('placa'))), 0, 10),
            from: self::date($request->query('de')),
            until: self::date($request->query('ate')),
            category: ServiceCategory::tryFrom($category)?->value,
            verified: VehicleMaintenanceHistory::normalizeFilter($request->query('verified')),
            attachments: array_key_exists($attachments, self::ATTACHMENT_OPTIONS) ? $attachments : null,
            warranty: array_key_exists($warranty, self::WARRANTY_OPTIONS) ? $warranty : null,
            sort: $sort,
            direction: in_array($direction, ['asc', 'desc'], true) ? $direction : ($sort === 'km' ? 'asc' : 'desc'),
        );
    }

    /**
     * Aplica os filtros e a ordenação.
     *
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(Builder|Relation $query): Builder|Relation
    {
        $this->applyWithoutProvenance($query)
            ->when($this->verified === VehicleMaintenanceHistory::FILTER_SEALED, fn (Builder|Relation $builder) => $builder->whereNotNull('verified_at'))
            ->when($this->verified === VehicleMaintenanceHistory::FILTER_DECLARED, fn (Builder|Relation $builder) => $builder->whereNull('verified_at'));

        return $query
            ->orderBy(self::SORT_COLUMNS[$this->sort], $this->direction)
            ->orderBy('id', $this->direction);
    }

    /**
     * Os mesmos filtros, sem o de procedência e sem ordenação: base das contagens do controle
     * Todas / Selo da oficina / Declaradas.
     *
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function applyWithoutProvenance(Builder|Relation $query): Builder|Relation
    {
        $today = CarbonImmutable::now()->startOfDay();

        return $query
            ->when($this->plate !== '', fn (Builder|Relation $builder) => $builder->whereHas(
                'vehicle',
                fn (Builder $vehicle) => $vehicle->where('license_plate', 'like', '%'.$this->plate.'%'),
            ))
            ->when($this->from !== null, fn (Builder|Relation $builder) => $builder->whereDate('maintenance_date', '>=', $this->from->toDateString()))
            ->when($this->until !== null, fn (Builder|Relation $builder) => $builder->whereDate('maintenance_date', '<=', $this->until->toDateString()))
            ->when($this->category !== null, fn (Builder|Relation $builder) => $builder->where('service_category', $this->category))
            ->when($this->attachments === 'com-nfe', fn (Builder|Relation $builder) => $builder->has('invoices'))
            ->when($this->attachments === 'sem-nfe', fn (Builder|Relation $builder) => $builder->doesntHave('invoices'))
            ->when($this->attachments === 'sem-fotos-depois', fn (Builder|Relation $builder) => $builder->whereDoesntHave(
                'photos',
                fn (Builder $photos) => $photos->where('stage', MaintenancePhoto::STAGE_AFTER),
            ))
            ->when($this->warranty === 'vigente', fn (Builder|Relation $builder) => $builder->whereHas(
                'warranties',
                fn (Builder $warranties) => $warranties->whereDate('ends_at', '>=', $today->toDateString()),
            ))
            ->when($this->warranty === 'vencendo', fn (Builder|Relation $builder) => $builder->whereHas(
                'warranties',
                fn (Builder $warranties) => $warranties
                    ->whereDate('ends_at', '>=', $today->toDateString())
                    ->whereDate('ends_at', '<=', $today->addDays(self::EXPIRING_WITHIN_DAYS)->toDateString()),
            ));
    }

    /**
     * Há algum filtro ativo (a ordenação não conta).
     */
    public function isFiltered(): bool
    {
        return $this->verified !== VehicleMaintenanceHistory::FILTER_ALL || $this->activeFilterCount() > 0;
    }

    /**
     * Filtros ativos no painel "Filtros" (placa, período, categoria, anexos e garantia). A
     * procedência tem o próprio controle e fica de fora.
     */
    public function activeFilterCount(): int
    {
        return count(array_filter([
            $this->plate !== '',
            $this->from !== null || $this->until !== null,
            $this->category !== null,
            $this->attachments !== null,
            $this->warranty !== null,
        ]));
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        $value = self::scalar($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}
