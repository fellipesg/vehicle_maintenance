<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleConsignmentService;
use App\Services\VehicleCatalogService;
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

    public function index(Request $request): View
    {
        $user = $request->user();

        $vehicles = $user->stockVehicles()
            ->withCount([
                'maintenances' => fn ($query) => $query->where('maintenances.tenant_id', $user->tenant_id),
            ])
            ->with(['activeConsignment' => fn ($query) => $query->where('garage_user_id', $user->id)])
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

    public function endConsignment(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('endConsignment', $vehicle);

        $data = $request->validate([
            'end_reason' => ['required', 'in:sold,owner_withdrew'],
        ], [
            'end_reason.in' => 'Informe se o veículo foi vendido ou devolvido ao proprietário.',
        ]);

        $service = app(VehicleConsignmentService::class);
        $consignment = $service->activeFor($request->user(), $vehicle);

        if ($consignment === null) {
            return back()->withErrors(['consignment' => 'Este veículo não está em consignação nesta garagem.']);
        }

        try {
            $service->end($consignment, $data['end_reason']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment' => $exception->getMessage()]);
        }

        return redirect()->route('garage.vehicles.index')
            ->with('success', 'Consignação encerrada. As manutenções registradas seguem no histórico do veículo.');
    }

    public function requestHistoryAccess(Request $request, Vehicle $vehicle, VehicleConsignmentService $consignments): RedirectResponse
    {
        Gate::authorize('endConsignment', $vehicle);

        $consignment = $consignments->activeFor($request->user(), $vehicle);

        if ($consignment === null) {
            return back()->withErrors(['consignment' => 'Este veículo não está em consignação nesta garagem.']);
        }

        try {
            $consignments->requestHistoryAccess($consignment);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pedido enviado ao proprietário. Ele pode liberar o histórico com um clique.');
    }

    public function show(Request $request, Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $user = $request->user();
        $seesFullHistory = Gate::allows('viewFullHistory', $vehicle);

        $vehicle->loadCount([
            'maintenances' => fn ($query) => $vehicle->restrictMaintenancesTo($query, $user),
            'maintenances as verified_maintenances_count' => fn ($query) => $vehicle
                ->restrictMaintenancesTo($query, $user)
                ->whereNotNull('verified_at'),
        ]);
        $vehicle->load([
            'plates' => fn ($q) => $q->orderByDesc('started_at')->orderByDesc('created_at'),
            'provenanceStripMaintenances' => fn ($query) => $vehicle->restrictMaintenancesTo($query, $user),
            'maintenances' => fn ($query) => $vehicle
                ->restrictMaintenancesTo($query, $user)
                ->with(['items', 'workshop', 'verifiedWorkshop', 'user', 'invoices']),
            'activeConsignment' => fn ($query) => $query->where('garage_user_id', $user->id),
        ]);

        return view('garage.vehicles.show', compact('vehicle', 'seesFullHistory'));
    }
}
