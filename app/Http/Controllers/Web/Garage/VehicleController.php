<?php

namespace App\Http\Controllers\Web\Garage;

use App\Enums\Portal;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AddsVehicleCovers;
use App\Http\Controllers\Web\Concerns\HandlesVehicleConsignment;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Http\Controllers\Web\Concerns\UpdatesVehicleDetails;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\VehicleCatalogService;
use App\Support\Vehicle\DealerStock;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    use AddsVehicleCovers;
    use HandlesVehicleConsignment;
    use ImportsVehicleFromCrlv;
    use RegistersVehicleWithOwnership;
    use UpdatesVehicleDetails;

    /**
     * Assistente "Adicionar ao estoque" (passos, rotas e textos do Lojista).
     */
    protected function vehicleEntryFlow(): VehicleEntryFlow
    {
        return VehicleEntryFlow::for(Portal::Dealer);
    }

    /**
     * Estoque: busca, filtros de procedência, ordenação, cards ou tabela e paginação (DealerStock).
     */
    public function index(Request $request): View
    {
        $stock = DealerStock::fromRequest($request);
        $stockTotal = $stock->total();

        return view('garage.vehicles.index', [
            'stock' => $stock,
            'stockTotal' => $stockTotal,
            'stockView' => DealerStock::viewFromRequest($request),
            'counts' => $stockTotal > 0 ? $stock->counts() : [],
            'vehicles' => $stock->query()->paginate(DealerStock::PER_PAGE)->withQueryString(),
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

    /**
     * Ficha do veículo (<x-vehicle.detail>). Abre só para veículo do estoque deste lojista cujo
     * histórico ele pode ver: dono atual ou consignação com a procuração aprovada. Chassi e RENAVAM
     * inteiros (e CRV e motor) só para o dono atual (VehiclePolicy::update), como na API; em
     * consignação saem parciais (App\Support\Vehicle\VehicleIdentifierMask).
     */
    public function show(Request $request, Vehicle $vehicle): View
    {
        $this->authorizeStockVehicle($request, $vehicle);

        $canAddMaintenance = Gate::allows('addMaintenance', $vehicle);
        $canEdit = Gate::allows('update', $vehicle);
        $identifiersMasked = ! $canEdit;
        $consignmentGrant = $canAddMaintenance ? null : $request->user()->consignmentGrantFor($vehicle);

        return view('garage.vehicles.show', compact('vehicle', 'canAddMaintenance', 'canEdit', 'identifiersMasked', 'consignmentGrant'));
    }

    /**
     * "Editar veículo e capas": só o dono atual (VehiclePolicy::update); consignação não edita.
     */
    public function edit(Request $request, Vehicle $vehicle, VehicleCatalogService $catalog): View
    {
        $this->authorizeStockVehicle($request, $vehicle);
        Gate::authorize('update', $vehicle);

        return view('garage.vehicles.edit', [
            'vehicle' => $vehicle,
            'catalog' => $catalog->all(),
            'coverMaxMb' => VehicleEntryFlow::COVER_MAX_KILOBYTES / 1024,
        ]);
    }

    public function update(Request $request, Vehicle $vehicle, VehicleCoverService $covers): RedirectResponse
    {
        $this->authorizeStockVehicle($request, $vehicle);
        Gate::authorize('update', $vehicle);

        $this->updateVehicleDetails($request, $vehicle, $covers);

        return redirect()->route('garage.vehicles.show', $vehicle)
            ->with('success', 'Veículo atualizado.');
    }

    private function authorizeStockVehicle(Request $request, Vehicle $vehicle): void
    {
        abort_unless($request->user()->canViewStockVehicleHistory($vehicle), 403);
    }
}
