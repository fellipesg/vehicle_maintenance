<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Portal;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Abas de perfil da lista (?perfil=): valor da URL => rótulo. Vazio mostra todos.
     *
     * @var array<string, string>
     */
    public const PROFILES = [
        '' => 'Todos',
        'proprietarios' => 'Proprietários',
        'lojistas' => 'Lojistas',
        'oficinas' => 'Oficinas',
        'administradores' => 'Administradores',
    ];

    /**
     * Colunas ordenáveis (?ordenar=) => coluna ou contagem no SQL.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'nome' => 'name',
        'veiculos' => 'current_vehicles_count',
        'manutencoes' => 'maintenances_count',
        'cadastro' => 'created_at',
    ];

    /**
     * Quantas manutenções lançadas aparecem no detalhe antes do "Ver todas".
     */
    private const RECENT_MAINTENANCES = 10;

    /**
     * Lista de usuários: busca por nome ou e-mail (?q=), aba de perfil (?perfil=), contas sem
     * coordenadas (?localizacao=sem-coordenadas, vindo do mapa), ordenação e 25 por página.
     */
    public function index(Request $request): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $profile = is_string($request->query('perfil')) && array_key_exists($request->query('perfil'), self::PROFILES)
            ? $request->query('perfil')
            : '';
        $withoutCoordinates = $request->query('localizacao') === 'sem-coordenadas';
        $sort = is_string($request->query('ordenar')) && array_key_exists($request->query('ordenar'), self::SORTS)
            ? $request->query('ordenar')
            : 'nome';
        $direction = $request->query('direcao') === 'desc' ? 'desc' : 'asc';

        $filtered = User::query()
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->when($withoutCoordinates, fn (Builder $query) => $query->where(
                fn (Builder $coordinates) => $coordinates->whereNull('latitude')->orWhereNull('longitude')
            ));

        $counts = $this->profileCounts(clone $filtered);

        $users = $this->applyProfile($filtered, $profile)
            ->withCount([
                // Postgres rejeita "boolean = 1": literal SQL true.
                'vehicles as current_vehicles_count' => fn (Builder $query) => $query->whereRaw('user_vehicles.is_current_owner = true'),
                'maintenances',
            ])
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'profile' => $profile,
            'profileCounts' => $counts,
            'withoutCoordinates' => $withoutCoordinates,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Detalhe por perfil. Métricas com nome explícito: "lançadas por esta conta" (user_id, o que a
     * conta registrou) e "nos veículos atuais" (histórico dos veículos de que ela é dona hoje, com
     * registros de donos anteriores). Oficina mostra a oficina vinculada e o que ela lançou.
     */
    public function show(User $user): View
    {
        $portal = $user->portal();

        $currentVehicles = $user->vehicles()
            ->where(function (Builder $query) use ($portal): void {
                // Postgres rejeita "boolean = 1": literal SQL true.
                $query->whereRaw('user_vehicles.is_current_owner = true');

                if ($portal === Portal::Dealer) {
                    $query->orWhere('user_vehicles.ownership_type', 'consignment');
                }
            })
            ->with('provenanceStripMaintenances')
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn (Builder $query) => $query->whereNotNull('verified_at'),
            ])
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        $workshop = $portal === Portal::Workshop ? $user->workshop()->first() : null;

        return view('admin.users.show', [
            'user' => $user,
            'portal' => $portal,
            'currentVehicles' => $currentVehicles,
            'currentVehicleMaintenanceCount' => $currentVehicles->sum('maintenances_count'),
            'launchedMaintenanceCount' => $user->maintenances()->count(),
            'recentLaunchedMaintenances' => $user->maintenances()
                ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
                ->withCount(['invoices', 'photos'])
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->limit(self::RECENT_MAINTENANCES)
                ->get(),
            'recentLimit' => self::RECENT_MAINTENANCES,
            'workshop' => $workshop,
            'workshopSealedCount' => $workshop === null
                ? 0
                : Maintenance::query()->where('verified_workshop_id', $workshop->id)->whereNotNull('verified_at')->count(),
        ]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.mb_strtolower($search).'%';

        $query->where(fn (Builder $terms) => $terms
            ->whereRaw('lower(name) like ?', [$like])
            ->orWhereRaw('lower(email) like ?', [$like]));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function applyProfile(Builder $query, string $profile): Builder
    {
        return match ($profile) {
            'proprietarios' => $query->where('user_type', Portal::Owner->value),
            'lojistas' => $query->where('user_type', Portal::Dealer->value),
            'oficinas' => $query->where('user_type', Portal::Workshop->value),
            'administradores' => $query->where('is_admin', true),
            default => $query,
        };
    }

    /**
     * Contagem de cada aba com a busca aplicada, numa consulta só.
     *
     * @param  Builder<User>  $query
     * @return array<string, int>
     */
    private function profileCounts(Builder $query): array
    {
        $totals = $query->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when user_type = 'user' then 1 else 0 end) as owners")
            ->selectRaw("sum(case when user_type = 'garage' then 1 else 0 end) as dealers")
            ->selectRaw("sum(case when user_type = 'workshop' then 1 else 0 end) as workshops")
            ->selectRaw('sum(case when is_admin = true then 1 else 0 end) as admins')
            ->first();

        return (new Collection([
            '' => $totals->total ?? 0,
            'proprietarios' => $totals->owners ?? 0,
            'lojistas' => $totals->dealers ?? 0,
            'oficinas' => $totals->workshops ?? 0,
            'administradores' => $totals->admins ?? 0,
        ]))->map(fn (mixed $count): int => (int) $count)->all();
    }
}
