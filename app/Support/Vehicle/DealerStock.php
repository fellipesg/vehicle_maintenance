<?php

namespace App\Support\Vehicle;

use App\Models\User;
use App\Support\VehiclePlateSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

/**
 * Lista do Estoque do Lojista (garage.vehicles.index): busca, filtros de procedência, ordenação e o
 * modo de exibição, todos na query string.
 *
 * - busca: marca e modelo, placa atual ou antiga e chassi (parcial, sem pontuação).
 * - filtro: selo (ao menos uma manutenção com Selo da oficina), declaradas (histórico só com
 *   declaradas), sem-historico (nenhuma manutenção) e consignacao (em consignação, em qualquer
 *   estado da procuração). Os três primeiros só olham veículos cujo histórico o lojista pode ver
 *   (dono atual ou procuração aprovada, como VehiclePolicy::viewMaintenances).
 * - ordem: "{coluna}_{sentido}" (entrada_desc, veiculo_asc, ano_desc, km_asc, manutencao_desc...).
 * - visao: cards | tabela, lembrada na sessão.
 *
 * Ex.: $stock = DealerStock::fromRequest($request);
 *      $vehicles = $stock->query()->paginate(DealerStock::PER_PAGE)->withQueryString();
 */
final class DealerStock
{
    public const FILTER_ALL = '';

    public const FILTER_SEALED = 'selo';

    public const FILTER_DECLARED_ONLY = 'declaradas';

    public const FILTER_WITHOUT_HISTORY = 'sem-historico';

    public const FILTER_CONSIGNMENT = 'consignacao';

    public const VIEW_CARDS = 'cards';

    public const VIEW_TABLE = 'tabela';

    public const PER_PAGE = 24;

    public const DEFAULT_SORT = 'entrada';

    public const DEFAULT_DIRECTION = 'desc';

    private const VIEW_SESSION_KEY = 'garage.stock_view';

    /**
     * Colunas ordenáveis e o sentido do primeiro clique no cabeçalho da tabela.
     *
     * @var array<string, string>
     */
    public const SORT_COLUMNS = [
        'entrada' => 'desc',
        'veiculo' => 'asc',
        'ano' => 'desc',
        'km' => 'asc',
        'manutencao' => 'desc',
    ];

    public function __construct(
        public readonly User $user,
        public readonly string $search = '',
        public readonly string $filter = self::FILTER_ALL,
        public readonly string $sort = self::DEFAULT_SORT,
        public readonly string $direction = self::DEFAULT_DIRECTION,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = $request->query('busca');
        $filter = $request->query('filtro');
        [$sort, $direction] = self::parseOrder($request->query('ordem'));

        return new self(
            $request->user(),
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            is_string($filter) && array_key_exists($filter, self::filterLabels()) ? $filter : self::FILTER_ALL,
            $sort,
            $direction,
        );
    }

    /**
     * Modo de exibição pedido na URL (?visao=) ou o último usado, guardado na sessão.
     */
    public static function viewFromRequest(Request $request): string
    {
        $requested = $request->query('visao');

        if (in_array($requested, [self::VIEW_CARDS, self::VIEW_TABLE], true)) {
            $request->session()->put(self::VIEW_SESSION_KEY, $requested);

            return $requested;
        }

        $remembered = $request->session()->get(self::VIEW_SESSION_KEY);

        return in_array($remembered, [self::VIEW_CARDS, self::VIEW_TABLE], true) ? $remembered : self::VIEW_CARDS;
    }

    /**
     * Rótulos dos filtros, na ordem dos chips.
     *
     * @return array<string, string>
     */
    public static function filterLabels(): array
    {
        return [
            self::FILTER_ALL => 'Todos',
            self::FILTER_SEALED => 'Com selo',
            self::FILTER_DECLARED_ONLY => 'Só declaradas',
            self::FILTER_WITHOUT_HISTORY => 'Sem histórico',
            self::FILTER_CONSIGNMENT => 'Consignação',
        ];
    }

    /**
     * Opções do select "Ordenar por" (valor de ?ordem=). A ordem atual entra na lista mesmo quando
     * veio de um clique no cabeçalho da tabela que não está entre as opções principais.
     *
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        $options = [
            'entrada_desc' => 'Entrada mais recente',
            'entrada_asc' => 'Entrada mais antiga',
            'veiculo_asc' => 'Marca e modelo (A a Z)',
            'ano_desc' => 'Ano mais novo',
            'km_asc' => 'Menor quilometragem',
            'manutencao_desc' => 'Manutenção mais recente',
        ];

        if (! array_key_exists($this->orderValue(), $options)) {
            $options[$this->orderValue()] = self::orderLabel($this->sort, $this->direction);
        }

        return $options;
    }

    public function orderValue(): string
    {
        return $this->sort.'_'.$this->direction;
    }

    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->filter !== self::FILTER_ALL;
    }

    /**
     * Estoque com a busca, o filtro, a ordem e o que os cards e a tabela mostram (procedência em
     * pontos, contagens e a data da última manutenção) carregados de uma vez.
     */
    public function query(): BelongsToMany
    {
        $query = $this->applyFilter($this->searched(), $this->filter)
            ->with('provenanceStripMaintenances')
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn (Builder $maintenances) => $maintenances->whereNotNull('verified_at'),
            ])
            ->withMax('maintenances', 'maintenance_date');

        return $this->applySort($query);
    }

    /**
     * Quantos veículos cada filtro mostra, com a busca atual.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (array_keys(self::filterLabels()) as $filter) {
            $counts[$filter] = $this->applyFilter($this->searched(), $filter)->count();
        }

        return $counts;
    }

    /**
     * Tamanho do estoque sem busca nem filtro (decide entre "estoque vazio" e "nada encontrado").
     */
    public function total(): int
    {
        return $this->user->stockVehicles()->count();
    }

    private function searched(): BelongsToMany
    {
        $query = $this->user->stockVehicles();

        if ($this->search === '') {
            return $query;
        }

        $text = '%'.mb_strtolower(str_replace(['%', '_', '\\'], ' ', $this->search)).'%';
        $identifier = VehiclePlateSearch::normalize($this->search);

        return $query->where(function (Builder $search) use ($text, $identifier): void {
            $search->whereRaw('LOWER(vehicles.brand) LIKE ?', [$text])
                ->orWhereRaw('LOWER(vehicles.model) LIKE ?', [$text])
                ->orWhereRaw("LOWER(vehicles.brand || ' ' || vehicles.model) LIKE ?", [$text]);

            if ($identifier !== '') {
                $search->orWhere('vehicles.license_plate', 'like', '%'.$identifier.'%')
                    ->orWhere('vehicles.chassis', 'like', '%'.$identifier.'%')
                    ->orWhereHas('plates', fn (Builder $plates) => $plates->where('plate', 'like', '%'.$identifier.'%'));
            }
        });
    }

    private function applyFilter(BelongsToMany $query, string $filter): BelongsToMany
    {
        return match ($filter) {
            self::FILTER_SEALED => $this->withVisibleHistory($query)
                ->whereHas('maintenances', fn (Builder $maintenances) => $maintenances->whereNotNull('verified_at')),
            self::FILTER_DECLARED_ONLY => $this->withVisibleHistory($query)
                ->whereHas('maintenances')
                ->whereDoesntHave('maintenances', fn (Builder $maintenances) => $maintenances->whereNotNull('verified_at')),
            self::FILTER_WITHOUT_HISTORY => $this->withVisibleHistory($query)->whereDoesntHave('maintenances'),
            self::FILTER_CONSIGNMENT => $query
                ->where('user_vehicles.ownership_type', 'consignment')
                ->whereRaw('user_vehicles.is_current_owner = false'),
            default => $query,
        };
    }

    /**
     * Dono atual, ou consignação com a procuração deste lojista aprovada (User::canViewStockVehicleHistory).
     */
    private function withVisibleHistory(BelongsToMany $query): BelongsToMany
    {
        return $query->where(function (Builder $visible): void {
            // Postgres rejects "boolean = 1"; use a SQL boolean literal.
            $visible->whereRaw('user_vehicles.is_current_owner = true')
                ->orWhereHas('accessGrants', fn (Builder $grants) => $grants
                    ->where('user_id', $this->user->id)
                    ->where('grant_type', 'consignment')
                    ->where('status', 'approved'));
        });
    }

    private function applySort(BelongsToMany $query): BelongsToMany
    {
        $direction = $this->direction;

        match ($this->sort) {
            'veiculo' => $query->orderBy('vehicles.brand', $direction)->orderBy('vehicles.model', $direction),
            'ano' => $query->orderBy('vehicles.year', $direction),
            'km' => $query->orderBy('vehicles.current_kilometers', $direction),
            'manutencao' => $query->orderBy('maintenances_max_maintenance_date', $direction),
            default => $query->orderBy('user_vehicles.created_at', $direction),
        };

        return $query->orderBy('vehicles.id', $direction);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseOrder(mixed $order): array
    {
        if (is_string($order) && preg_match('/^([a-z]+)_(asc|desc)$/', $order, $match) === 1 && array_key_exists($match[1], self::SORT_COLUMNS)) {
            return [$match[1], $match[2]];
        }

        return [self::DEFAULT_SORT, self::DEFAULT_DIRECTION];
    }

    private static function orderLabel(string $sort, string $direction): string
    {
        $descending = $direction === 'desc';

        return match ($sort) {
            'veiculo' => $descending ? 'Marca e modelo (Z a A)' : 'Marca e modelo (A a Z)',
            'ano' => $descending ? 'Ano mais novo' : 'Ano mais antigo',
            'km' => $descending ? 'Maior quilometragem' : 'Menor quilometragem',
            'manutencao' => $descending ? 'Manutenção mais recente' : 'Manutenção mais antiga',
            default => $descending ? 'Entrada mais recente' : 'Entrada mais antiga',
        };
    }
}
