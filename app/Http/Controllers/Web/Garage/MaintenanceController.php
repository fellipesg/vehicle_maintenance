<?php

namespace App\Http\Controllers\Web\Garage;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Rules\InvoiceFile;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use App\Services\Vehicle\VehicleMileageService;
use App\Support\Maintenance\MaintenanceListFilters;
use App\Support\Vehicle\MaintenanceMileageContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use StoresMaintenanceInvoices;

    /**
     * Filtros da lista (query string "filtro"), na ordem em que aparecem.
     *
     * @var array<string, string>
     */
    private const FILTERS = [
        'todas' => 'Todas do estoque',
        'selo' => 'Com Selo da oficina',
        'declaradas' => 'Declaradas',
        'minhas' => 'Registradas por mim',
    ];

    /**
     * Separador entre o nome e a cidade da oficina nas sugestões do campo Oficina.
     */
    public const WORKSHOP_LABEL_SEPARATOR = ' · ';

    /**
     * Lista o histórico dos veículos do estoque (Selo da oficina, declaradas por donos anteriores e as do
     * lojista), só onde ele pode ver o histórico: dono atual ou consignação com procuração aprovada.
     * "Registradas por mim" filtra pelo autor do registro, inclusive em veículos que já saíram do estoque.
     * O filtro "veiculo" (x-maintenance.list) recorta um veículo do estoque.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $historyVehicleIds = $user->stockVehicleIdsWithVisibleHistory();
        $requestedFilter = (string) $request->query('filtro', 'todas');
        $filter = array_key_exists($requestedFilter, self::FILTERS) ? $requestedFilter : 'todas';
        $vehicleId = MaintenanceListFilters::fromRequest($request)->vehicleId;

        $filters = [];
        foreach (self::FILTERS as $key => $label) {
            $filters[$key] = [
                'label' => $label,
                'count' => $this->filteredMaintenances($key, $user, $historyVehicleIds, $vehicleId)->count(),
            ];
        }

        $maintenances = $this->filteredMaintenances($filter, $user, $historyVehicleIds, $vehicleId)
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->withCount(['invoices', 'photos'])
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $vehicleOptions = $user->stockVehicles()
            ->orderBy('vehicles.brand')
            ->orderBy('vehicles.model')
            ->get()
            ->filter(fn (Vehicle $vehicle): bool => in_array((int) $vehicle->id, $historyVehicleIds, true))
            ->values();

        return view('garage.maintenances.index', compact('maintenances', 'filters', 'filter', 'historyVehicleIds', 'vehicleOptions'));
    }

    /**
     * Formulário "Registrar manutenção". Com ?vehicle_id= (vindo da ficha) o veículo já vem escolhido
     * e Salvar e Cancelar voltam à ficha.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        // Registrar manutenção move o hodômetro, então só o dono atual pode (VehiclePolicy::addMaintenance).
        // Consignação fica fora do select e a tela explica o motivo.
        [$vehicles, $consignmentVehicles] = $user->stockVehicles()
            ->with(['maintenances' => fn ($query) => $query->select(MaintenanceMileageContext::COLUMNS)])
            ->orderBy('vehicles.brand')
            ->orderBy('vehicles.model')
            ->get()
            ->partition(fn (Vehicle $vehicle): bool => ! $user->holdsOnConsignment($vehicle)
                || Gate::forUser($user)->allows('addMaintenance', $vehicle));

        $requestedVehicleId = $request->query('vehicle_id');
        $selectedVehicle = is_scalar($requestedVehicleId)
            ? $vehicles->first(fn (Vehicle $vehicle): bool => (string) $vehicle->id === (string) $requestedVehicleId)
            : null;

        return view('garage.maintenances.create', [
            'vehicles' => $vehicles->values(),
            'consignmentVehicles' => $consignmentVehicles->values(),
            'workshops' => Workshop::query()->orderBy('name')->get(['id', 'name', 'city']),
            'selectedVehicle' => $selectedVehicle,
            'mileage' => MaintenanceMileageContext::for($vehicles->values()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'workshop_id' => ['nullable', 'exists:workshops,id'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'workshop_name' => ['nullable', 'string', 'max:255'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_category' => ['required', Rule::in(ServiceCategory::values())],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'return_to' => ['nullable', 'in:vehicle'],
        ]);

        $returnsToVehicle = ($data['return_to'] ?? null) === 'vehicle';
        unset($data['return_to']);

        if (empty($data['workshop_id']) && filled($data['workshop_name'] ?? null)) {
            $data['workshop_id'] = $this->networkWorkshopIdFor((string) $data['workshop_name']);
        }

        if (! empty($data['workshop_id'])) {
            $workshop = Workshop::find($data['workshop_id']);
            $data['workshop_name'] = $workshop?->name;
        }

        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['is_manufacturer_required'] = $request->boolean('is_manufacturer_required');

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        Gate::authorize('addMaintenance', $vehicle);

        app(VehicleMileageService::class)->assertMaintenanceKilometers(
            $vehicle,
            (int) $data['kilometers'],
            $data['maintenance_date'],
        );

        $result = $this->storeMaintenanceWithInvoices(
            $request,
            function () use ($data, $request) {
                $maintenance = Maintenance::create($data);
                app(MaintenanceVerificationStamper::class)->stamp($maintenance, $request->user());
                app(VehicleMileageService::class)->applyMaintenanceKilometers(
                    Vehicle::findOrFail($data['vehicle_id']),
                    (int) $data['kilometers'],
                );

                return $maintenance;
            },
        );

        // Quem começou pela ficha volta a ela, com a manutenção nova em destaque (:target do card).
        $redirect = $returnsToVehicle
            ? redirect()->to(route('garage.vehicles.show', $vehicle).'#manutencao-'.$result['maintenance']->id)
            : redirect()->route('garage.maintenances.index');

        return $this->redirectWithInvoiceFeedback(
            $redirect,
            $result['items_created'],
            $result['warnings'],
        );
    }

    /**
     * Detalhe da manutenção (maintenances._detail), só para veículo do estoque cujo histórico o lojista
     * pode ver: dono atual ou consignação com procuração aprovada.
     */
    public function show(Request $request, Maintenance $maintenance): View
    {
        $vehicle = $maintenance->vehicle()->first();

        abort_unless($vehicle !== null && $request->user()->canViewStockVehicleHistory($vehicle), 403);

        $maintenance->setRelation('vehicle', $vehicle);

        return view('garage.maintenances.show', [
            'maintenance' => $maintenance,
            'vehicle' => $vehicle,
            'canAddMaintenance' => Gate::allows('addMaintenance', $vehicle),
        ]);
    }

    /**
     * Oficina da rede escrita no campo Oficina: a sugestão "Nome · Cidade" ou só o nome, quando é
     * único. Sem correspondência, fica só o nome digitado (oficina fora da rede).
     */
    private function networkWorkshopIdFor(string $typedName): ?int
    {
        $typed = trim($typedName);
        [$name, $city] = str_contains($typed, trim(self::WORKSHOP_LABEL_SEPARATOR))
            ? array_map('trim', explode(trim(self::WORKSHOP_LABEL_SEPARATOR), $typed, 2))
            : [$typed, null];

        if ($name === '') {
            return null;
        }

        $matches = Workshop::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when(filled($city), fn (Builder $query) => $query->whereRaw('LOWER(city) = ?', [mb_strtolower((string) $city)]))
            ->limit(2)
            ->pluck('id');

        return $matches->count() === 1 ? (int) $matches->first() : null;
    }

    /**
     * "minhas" recorta pelo autor (user_id), não pelo tenant: a OS da oficina grava o tenant do dono atual,
     * que é o do lojista quando o veículo está no estoque.
     *
     * @param  list<int>  $historyVehicleIds
     * @return Builder<Maintenance>
     */
    private function filteredMaintenances(string $filter, User $user, array $historyVehicleIds, ?int $vehicleId = null): Builder
    {
        $query = $filter === 'minhas'
            ? Maintenance::query()->where('user_id', $user->id)
            : Maintenance::query()->whereIn('vehicle_id', $historyVehicleIds);

        $query->when($vehicleId !== null, fn (Builder $scoped) => $scoped->where('vehicle_id', $vehicleId));

        return match ($filter) {
            'selo' => $query->whereNotNull('verified_at'),
            'declaradas' => $query->whereNull('verified_at'),
            default => $query,
        };
    }
}
