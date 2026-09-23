<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Services\VehicleCatalogService;
use App\Support\AppStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class VehicleController extends Controller
{
    use ImportsVehicleFromCrlv;
    use RegistersVehicleWithOwnership;

    protected function vehicleCreateRoute(): string
    {
        return 'garage.vehicles.create';
    }

    protected function vehiclePreviewRoute(): string
    {
        return 'garage.vehicles.import.preview';
    }

    protected function vehicleClaimPreviewRoute(): string
    {
        return 'garage.vehicles.claim.preview';
    }

    protected function vehicleStoreRoute(): string
    {
        return 'garage.vehicles.store';
    }

    protected function vehicleClaimStoreRoute(): string
    {
        return 'garage.vehicles.claim.store';
    }

    protected function vehiclePreviewView(): string
    {
        return 'garage.vehicles.preview-import';
    }

    protected function vehicleClaimPreviewView(): string
    {
        return 'garage.vehicles.preview-claim';
    }

    protected function vehicleClaimView(): string
    {
        return 'garage.vehicles.claim';
    }

    protected function vehicleClaimImportRoute(): string
    {
        return 'garage.vehicles.claim.import-crlv';
    }

    protected function vehicleShowRoute(): string
    {
        return 'garage.vehicles.show';
    }

    protected function vehicleClaimRoute(): string
    {
        return 'garage.vehicles.claim';
    }

    protected function vehicleConsignmentRoute(): string
    {
        return 'garage.vehicles.consignment';
    }

    public function index(Request $request): View
    {
        $vehicles = $request->user()->currentVehicles()
            ->withCount('maintenances')
            ->get();

        return view('garage.vehicles.index', compact('vehicles'));
    }

    public function create(VehicleCatalogService $catalog): View
    {
        return view('garage.vehicles.create', [
            'catalog' => $catalog->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->registerVehicle($request);
    }

    public function claim(Request $request): RedirectResponse
    {
        return $this->claimVehicle($request);
    }

    public function showConsignmentForm(Request $request): View|RedirectResponse
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null || $import->mode !== 'consignment') {
            return redirect()->route('garage.vehicles.index');
        }

        return view('garage.vehicles.consignment', [
            'pending' => $import,
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null || $import->mode !== 'consignment') {
            return redirect()->route('garage.vehicles.index');
        }

        $request->validate([
            'power_of_attorney' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('power_of_attorney')->store('procuracoes', AppStorage::diskName());
        $crlv = $import->toParseResult();
        $ownership = app(VehicleOwnershipService::class);

        try {
            if ($import->vehicle_id !== null) {
                $vehicle = Vehicle::findOrFail($import->vehicle_id);
                $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
                $ownership->attachConsignmentUser($request->user(), $vehicle, $crlv);
                $this->finishCrlvImport($request, $import);

                return redirect()->route('garage.vehicles.index')
                    ->with('success', 'Procuração enviada para análise.');
            }

            $vehicle = $ownership->registerNew($request->user(), $import->pending_vehicle_data ?? [], $crlv, 'consignment');
            $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
            $this->finishCrlvImport($request, $import);

            return redirect()->route('garage.vehicles.index')
                ->with('success', 'Veículo adicionado em consignação.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['power_of_attorney' => $exception->getMessage()]);
        }
    }

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $vehicle->loadCount([
            'maintenances',
            'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
        ]);
        $vehicle->load([
            'plates' => fn ($q) => $q->orderByDesc('started_at')->orderByDesc('created_at'),
            'provenanceStripMaintenances',
            'maintenances' => fn ($query) => $query->with(['items', 'workshop', 'verifiedWorkshop', 'user', 'invoices']),
        ]);

        return view('garage.vehicles.show', compact('vehicle'));
    }
}
