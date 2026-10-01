<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\VehiclePlateSearch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Manutenções da plataforma inteira (sem escopo de tenant). Filtros na query string:
 * - q: placa (atual ou antiga, via VehiclePlateSearch), marca/modelo, oficina ou tipo de serviço;
 * - de / ate: período pela data do serviço (AAAA-MM-DD);
 * - verified: '1' Selo da oficina, '0' Declaradas, vazio Todas (o segmentado mostra a contagem de
 *   cada um com os outros filtros aplicados);
 * - usuario / veiculo / oficina: ids vindos de outras telas (lançadas por uma conta, histórico de um
 *   veículo, OS de uma oficina), mostrados como filtros removíveis;
 * - ordenar=data e direcao: a mais recente primeiro por padrão.
 *
 * Com X-Requested-With (resources/js/admin-maintenances-filters.js) devolve só a tabela.
 */
class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filtersFrom($request);

        $filtered = Maintenance::query()->where(fn (Builder $query) => $this->applyFilters($query, $filters));
        $counts = $this->provenanceCounts(clone $filtered);

        $maintenances = $filtered
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->when($filters['verified'] === '1', fn (Builder $query) => $query->whereNotNull('verified_at'))
            ->when($filters['verified'] === '0', fn (Builder $query) => $query->whereNull('verified_at'))
            ->orderBy('maintenance_date', $filters['direction'])
            ->orderBy('id', $filters['direction'])
            ->paginate(25)
            ->withQueryString();

        $viewData = [
            'maintenances' => $maintenances,
            'verified' => $filters['verified'] === '' ? null : $filters['verified'],
            'filters' => $filters,
            'counts' => $counts,
            'contextFilters' => $this->contextFilters($filters),
        ];

        if ($request->ajax()) {
            return view('admin.maintenances._results', $viewData);
        }

        return view('admin.maintenances.index', $viewData);
    }

    /**
     * @return array{q: string, from: ?CarbonImmutable, to: ?CarbonImmutable, verified: string, user: ?int, vehicle: ?int, workshop: ?int, direction: string}
     */
    private function filtersFrom(Request $request): array
    {
        $from = $this->dateFrom($request->query('de'));
        $to = $this->dateFrom($request->query('ate'));

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'q' => is_string($request->query('q')) ? trim($request->query('q')) : '',
            'from' => $from,
            'to' => $to,
            'verified' => in_array($request->query('verified'), ['1', '0'], true) ? $request->query('verified') : '',
            'user' => $this->idFrom($request->query('usuario')),
            'vehicle' => $this->idFrom($request->query('veiculo')),
            'workshop' => $this->idFrom($request->query('oficina')),
            'direction' => $request->query('ordenar') === 'data' && $request->query('direcao') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * Todos os filtros menos a procedência, que é o que o segmentado conta.
     *
     * @param  Builder<Maintenance>  $query
     * @param  array{q: string, from: ?CarbonImmutable, to: ?CarbonImmutable, user: ?int, vehicle: ?int, workshop: ?int}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['from'] !== null, fn (Builder $builder) => $builder->whereDate('maintenance_date', '>=', $filters['from']->toDateString()))
            ->when($filters['to'] !== null, fn (Builder $builder) => $builder->whereDate('maintenance_date', '<=', $filters['to']->toDateString()))
            ->when($filters['user'] !== null, fn (Builder $builder) => $builder->where('user_id', $filters['user']))
            ->when($filters['vehicle'] !== null, fn (Builder $builder) => $builder->where('vehicle_id', $filters['vehicle']))
            ->when($filters['workshop'] !== null, fn (Builder $builder) => $builder->where(fn (Builder $workshop) => $workshop
                ->where('workshop_id', $filters['workshop'])
                ->orWhere('verified_workshop_id', $filters['workshop'])))
            ->when($filters['q'] !== '', fn (Builder $builder) => $this->applySearch($builder, $filters['q']));
    }

    /**
     * Placa (atual ou antiga), chassi e RENAVAM pelo mesmo buscador da busca de veículo; marca,
     * modelo, oficina e tipo de serviço por trecho, sem diferenciar maiúsculas.
     *
     * @param  Builder<Maintenance>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $lookup = VehiclePlateSearch::findByIdentifier($search);
        $like = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $terms) use ($lookup, $like): void {
            if ($lookup !== null) {
                $terms->orWhere('vehicle_id', $lookup->vehicle->id);
            }

            $terms
                ->orWhereRaw('lower(maintenance_type) like ?', [$like])
                ->orWhereRaw('lower(workshop_name) like ?', [$like])
                ->orWhereHas('workshop', fn (Builder $workshop) => $workshop->whereRaw('lower(name) like ?', [$like]))
                ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle
                    ->whereRaw('lower(license_plate) like ?', [$like])
                    ->orWhereRaw('lower(brand) like ?', [$like])
                    ->orWhereRaw('lower(model) like ?', [$like]));
        });
    }

    /**
     * @param  Builder<Maintenance>  $query
     * @return array{'': int, '1': int, '0': int}
     */
    private function provenanceCounts(Builder $query): array
    {
        $totals = $query->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when verified_at is not null then 1 else 0 end) as sealed')
            ->first();

        $total = (int) ($totals->total ?? 0);
        $sealed = (int) ($totals->sealed ?? 0);

        return ['' => $total, '1' => $sealed, '0' => $total - $sealed];
    }

    /**
     * Filtros que vêm de outras telas (conta, veículo, oficina), com o nome para o chip removível.
     *
     * @param  array{user: ?int, vehicle: ?int, workshop: ?int}  $filters
     * @return list<array{param: string, label: string}>
     */
    private function contextFilters(array $filters): array
    {
        $chips = [];

        if ($filters['user'] !== null) {
            $name = User::query()->whereKey($filters['user'])->value('name');
            $chips[] = ['param' => 'usuario', 'label' => 'Lançadas por '.($name ?? 'conta #'.$filters['user'])];
        }

        if ($filters['vehicle'] !== null) {
            $vehicle = Vehicle::query()->find($filters['vehicle'], ['id', 'brand', 'model', 'license_plate']);
            $vehicleLabel = $vehicle ? trim($vehicle->brand.' '.$vehicle->model.' '.$vehicle->license_plate) : 'veículo #'.$filters['vehicle'];
            $chips[] = ['param' => 'veiculo', 'label' => 'Veículo '.$vehicleLabel];
        }

        if ($filters['workshop'] !== null) {
            $name = Workshop::query()->whereKey($filters['workshop'])->value('name');
            $chips[] = ['param' => 'oficina', 'label' => 'Oficina '.($name ?? '#'.$filters['workshop'])];
        }

        return $chips;
    }

    private function dateFrom(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function idFrom(mixed $value): ?int
    {
        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : null;
    }
}
