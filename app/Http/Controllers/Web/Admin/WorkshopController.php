<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkshopController extends Controller
{
    /**
     * Filtro de localização (?localizacao=): valor da URL => rótulo. Vazio mostra todas.
     *
     * @var array<string, string>
     */
    public const LOCATIONS = [
        '' => 'Todas',
        'no-mapa' => 'No mapa',
        'sem-coordenadas' => 'Sem coordenadas',
    ];

    /**
     * Colunas ordenáveis (?ordenar=) => coluna ou contagem no SQL.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'nome' => 'name',
        'cidade' => 'city',
        'selos' => 'sealed_maintenances_count',
    ];

    /**
     * Oficinas da plataforma: busca por nome ou cidade (?q=), situação no mapa (?localizacao=),
     * quantas manutenções cada uma verificou (Selo da oficina) e ordenação.
     */
    public function index(Request $request): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $location = is_string($request->query('localizacao')) && array_key_exists($request->query('localizacao'), self::LOCATIONS)
            ? $request->query('localizacao')
            : '';
        $sort = is_string($request->query('ordenar')) && array_key_exists($request->query('ordenar'), self::SORTS)
            ? $request->query('ordenar')
            : 'nome';
        $direction = in_array($request->query('direcao'), ['asc', 'desc'], true)
            ? $request->query('direcao')
            : ($sort === 'selos' ? 'desc' : 'asc');

        $searched = Workshop::query()->when($search !== '', function (Builder $query) use ($search): void {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn (Builder $terms) => $terms
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(city) like ?', [$like]));
        });

        $locationCounts = $this->locationCounts(clone $searched);

        $workshops = $searched
            ->when($location === 'no-mapa', fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
            ->when($location === 'sem-coordenadas', fn (Builder $query) => $query->where(
                fn (Builder $coordinates) => $coordinates->whereNull('latitude')->orWhereNull('longitude')
            ))
            ->addSelect(['sealed_maintenances_count' => Maintenance::query()
                ->selectRaw('count(*)')
                ->whereColumn('verified_workshop_id', 'workshops.id')
                ->whereNotNull('verified_at'),
            ])
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.workshops.index', [
            'workshops' => $workshops,
            'search' => $search,
            'location' => $location,
            'locationCounts' => $locationCounts,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * @param  Builder<Workshop>  $query
     * @return array<string, int>
     */
    private function locationCounts(Builder $query): array
    {
        $totals = $query->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when latitude is not null and longitude is not null then 1 else 0 end) as on_map')
            ->first();

        $total = (int) ($totals->total ?? 0);
        $onMap = (int) ($totals->on_map ?? 0);

        return ['' => $total, 'no-mapa' => $onMap, 'sem-coordenadas' => $total - $onMap];
    }
}
