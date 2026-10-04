<?php

namespace App\Http\Controllers\Web\User;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Policies\MaintenancePolicy;
use App\Rules\InvoiceFile;
use App\Rules\RequiresInvoiceWhenWorkshopAssigned;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use App\Services\Maintenance\WorkshopReviewService;
use App\Services\User\OwnerDashboard;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Workshop\WorkshopLeadRecorder;
use App\Support\AppStorage;
use App\Support\Maintenance\MaintenanceListFilters;
use App\Support\Vehicle\MaintenanceMileageContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manutenções do proprietário, renderizadas no servidor com o mesmo escopo e as mesmas regras da
 * API (/api/v1/maintenances): a lista é a do tenant da conta; ver, editar e excluir seguem a
 * MaintenancePolicy (só as declaradas desta conta e só enquanto ela for a dona atual do veículo;
 * as com Selo da oficina, só pela oficina que aplicou o selo): quem vendeu o carro não mexe no
 * histórico do comprador.
 */
class MaintenanceController extends Controller
{
    use StoresMaintenanceInvoices;

    public const PER_PAGE = 15;

    public function __construct(
        private readonly MaintenancePhotoService $photos,
        private readonly VehicleMileageService $mileage,
    ) {}

    /**
     * Lista com filtros na query string (?veiculo= e ?verified=), contagens por procedência e
     * paginação, agrupada por mês no x-maintenance.list.
     */
    public function index(Request $request, OwnerDashboard $owner): View
    {
        $user = $request->user();
        $filters = MaintenanceListFilters::fromRequest($request);

        // As contagens do filtro de procedência respeitam o veículo escolhido.
        $countScope = (new MaintenanceListFilters(vehicleId: $filters->vehicleId))->apply($owner->maintenancesOf($user));
        $total = (clone $countScope)->count();
        $sealed = (clone $countScope)->whereNotNull('verified_at')->count();

        $maintenances = $filters->apply($owner->maintenancesOf($user))
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->withCount(['invoices', 'photos'])
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $vehicles = Vehicle::query()
            ->whereIn('id', $owner->maintenancesOf($user)->select('vehicle_id'))
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        return view('user.maintenances.index', [
            'maintenances' => $maintenances,
            'vehicles' => $vehicles,
            'counts' => ['' => $total, '1' => $sealed, '0' => $total - $sealed],
            'hasVehicles' => $user->currentVehicles()->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        $vehicles = $this->ownedVehicles($request->user());
        $selectedVehicle = $vehicles->firstWhere('id', (int) $request->query('vehicle_id'));
        $workshops = $this->workshops();

        return view('user.maintenances.create', [
            'vehicles' => $vehicles,
            'workshops' => $workshops,
            'selectedVehicle' => $selectedVehicle,
            'selectedWorkshopId' => $workshops->firstWhere('id', (int) $request->query('workshop_id'))?->id,
            'mileage' => MaintenanceMileageContext::for($vehicles),
            'cancelUrl' => $selectedVehicle !== null
                ? route('user.vehicles.show', $selectedVehicle)
                : route('user.maintenances.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $this->validateMaintenance($request);
        $returnToVehicle = ($data['return_to'] ?? null) === 'vehicle';
        unset($data['return_to'], $data['invoices']);

        $data = $this->withWorkshopName($data);
        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['is_manufacturer_required'] = $request->boolean('is_manufacturer_required');

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        Gate::authorize('addMaintenance', $vehicle);

        $this->mileage->assertMaintenanceKilometers(
            $vehicle,
            (int) $data['kilometers'],
            $data['maintenance_date'],
        );

        $workshopContact = $data['workshop_contact'] ?? null;
        unset($data['workshop_contact']);

        $result = $this->storeMaintenanceWithInvoices(
            $request,
            function () use ($data, $request, $workshopContact) {
                $maintenance = Maintenance::create($data);
                $maintenance = app(MaintenanceVerificationStamper::class)->stamp($maintenance, $request->user());
                app(WorkshopLeadRecorder::class)->rememberContact($maintenance, $workshopContact);
                $this->mileage->applyMaintenanceKilometers(
                    Vehicle::findOrFail($data['vehicle_id']),
                    (int) $data['kilometers'],
                );

                return $maintenance;
            },
        );

        // Quem veio da ficha do veículo volta para ela, na aba Histórico.
        $redirect = $returnToVehicle
            ? redirect()->to(route('user.vehicles.show', $vehicle).'#historico')
            : redirect()->route('user.maintenances.show', $result['maintenance']);

        return $this->redirectWithInvoiceFeedback(
            $redirect,
            $result['items_created'],
            $result['warnings'],
        );
    }

    public function show(Maintenance $maintenance): View
    {
        Gate::authorize('view', $maintenance);

        $maintenance->load(['vehicle', 'items.warranty', 'generalWarranty', 'invoices', 'checklists', 'workshop', 'verifiedWorkshop', 'user', 'photos']);
        $vehicle = $maintenance->vehicle;
        $updateDecision = Gate::inspect('update', $maintenance);

        return view('user.maintenances.show', [
            'maintenance' => $maintenance,
            'canViewVehicle' => $vehicle !== null && Gate::allows('view', $vehicle),
            'canUpdate' => $updateDecision->allowed(),
            'canDelete' => Gate::allows('delete', $maintenance),
            // Declarada por esta conta num veículo que já saiu dela (vendido ou desvinculado).
            'vehicleLeftAccount' => $updateDecision->code() === MaintenancePolicy::DENIED_NOT_CURRENT_OWNER,
        ]);
    }

    /**
     * Correção de uma manutenção declarada. O veículo não muda; a quilometragem continua presa aos
     * registros vizinhos (VehicleMileageService, sem contar esta manutenção).
     */
    public function edit(Maintenance $maintenance): View
    {
        Gate::authorize('update', $maintenance);

        $maintenance->load(['vehicle', 'invoices']);
        $vehicle = $maintenance->vehicle;
        $vehicle?->load(['maintenances' => fn ($query) => $query->select(MaintenanceMileageContext::COLUMNS)]);

        return view('user.maintenances.edit', [
            'maintenance' => $maintenance,
            'workshops' => $this->workshops(),
            'mileage' => $vehicle !== null ? MaintenanceMileageContext::for(new Collection([$vehicle]), $maintenance) : [],
            'canViewVehicle' => $vehicle !== null && Gate::allows('view', $vehicle),
        ]);
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('update', $maintenance);

        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $this->validateMaintenance($request, $maintenance);
        unset($data['invoices'], $data['workshop_contact']);

        $data = $this->withWorkshopName($data);
        $previousWorkshopId = $maintenance->workshop_id;
        $data['is_manufacturer_required'] = $request->boolean('is_manufacturer_required');

        $vehicle = $maintenance->vehicle;

        if ($vehicle !== null) {
            $this->mileage->assertMaintenanceKilometers(
                $vehicle,
                (int) $data['kilometers'],
                $data['maintenance_date'],
                $maintenance,
            );
        }

        $maintenance->update($data);
        app(WorkshopReviewService::class)->syncAfterDeclaration($maintenance->fresh(), $previousWorkshopId);
        $maintenance->refresh();

        if ($vehicle !== null) {
            $this->mileage->refreshCurrentKilometers($vehicle->fresh());
        }

        $result = $this->processMaintenanceInvoices($request, $maintenance);

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('user.maintenances.show', $maintenance),
            $result['items_created'],
            $result['warnings'],
            $result['items_skipped'],
            'Manutenção atualizada.',
        );
    }

    /**
     * Exclui de vez uma manutenção declarada, com as fotos e as notas fiscais, e recalcula a
     * quilometragem atual do veículo.
     */
    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('delete', $maintenance);

        $maintenance->loadMissing(['photos', 'invoices', 'vehicle']);

        foreach ($maintenance->photos as $photo) {
            $this->photos->delete($photo);
        }

        foreach ($maintenance->invoices as $invoice) {
            if (AppStorage::disk()->exists($invoice->file_path)) {
                AppStorage::disk()->delete($invoice->file_path);
            }
        }

        $vehicle = $maintenance->vehicle;
        $maintenance->delete();

        if ($vehicle !== null) {
            $this->mileage->refreshCurrentKilometers($vehicle->fresh());
        }

        $redirect = $vehicle !== null && Gate::allows('view', $vehicle)
            ? redirect()->to(route('user.vehicles.show', $vehicle).'#historico')
            : redirect()->route('user.maintenances.index');

        return $redirect->with('success', 'Manutenção excluída.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMaintenance(Request $request, ?Maintenance $maintenance = null): array
    {
        $existingInvoices = $maintenance?->invoices()->count() ?? 0;

        $rules = [
            'workshop_id' => array_values(array_filter([
                'nullable',
                'exists:workshops,id',
                $maintenance?->rejected_workshop_id !== null ? Rule::notIn([$maintenance->rejected_workshop_id]) : null,
            ])),
            'workshop_contact' => ['nullable', 'string', 'max:255'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'workshop_name' => ['nullable', 'string', 'max:255'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_category' => ['required', Rule::in(ServiceCategory::values())],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => [
                $existingInvoices === 0 ? 'required_with:workshop_id' : 'nullable',
                'nullable',
                'array',
                new RequiresInvoiceWhenWorkshopAssigned(
                    workshopId: $request->filled('workshop_id') ? (int) $request->input('workshop_id') : null,
                    existingInvoiceCount: $existingInvoices,
                ),
            ],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
        ];

        if ($maintenance === null) {
            $rules['vehicle_id'] = ['required', 'exists:vehicles,id'];
            $rules['return_to'] = ['nullable', Rule::in(['vehicle'])];
        }

        return $request->validate($rules, [
            'invoices.*.uploaded' => 'Falha ao enviar o arquivo. Verifique se o PDF ou XML não está corrompido e se o tamanho está dentro do limite do servidor.',
            'invoices.*.mimes' => 'Apenas arquivos PDF ou XML são aceitos.',
            'invoices.*.max' => 'Cada arquivo pode ter no máximo 10 MB.',
            'workshop_id.not_in' => 'Essa oficina informou que não fez este serviço. Escolha outra oficina ou deixe sem oficina da rede.',
        ]);
    }

    /**
     * Com uma oficina da rede escolhida, o nome gravado é o dela.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withWorkshopName(array $data): array
    {
        if (! empty($data['workshop_id'])) {
            $data['workshop_name'] = Workshop::find($data['workshop_id'])?->name;
        }

        return $data;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function ownedVehicles(User $user): Collection
    {
        return $user->currentVehicles()
            ->with(['maintenances' => fn ($query) => $query->select(MaintenanceMileageContext::COLUMNS)])
            ->orderBy('brand')
            ->orderBy('model')
            ->get();
    }

    /**
     * @return Collection<int, Workshop>
     */
    private function workshops(): Collection
    {
        return Workshop::query()
            ->orderBy('name')
            ->get(['id', 'name', 'neighborhood', 'city', 'state']);
    }
}
