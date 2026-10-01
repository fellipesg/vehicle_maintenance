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
use App\Services\Vehicle\VehicleConsignmentService;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\VehicleCatalogService;
use App\Support\Vehicle\DealerStock;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

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
     * Ficha do veículo (<x-vehicle.detail>). Abre para qualquer veículo do estoque deste lojista,
     * inclusive a consignação sem liberação do histórico: ela mostra só o que a própria loja
     * registrou (Vehicle::restrictHistoryTo). Chassi e RENAVAM inteiros (e CRV e motor) só para
     * o dono atual (VehiclePolicy::update), como na API; em consignação saem parciais
     * (App\Support\Vehicle\VehicleIdentifierMask).
     */
    public function show(Request $request, Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $user = $request->user();
        $canAddMaintenance = Gate::allows('addMaintenance', $vehicle);
        $canEdit = Gate::allows('update', $vehicle);
        $identifiersMasked = ! $canEdit;
        $consignment = $user->consignmentFor($vehicle);

        $vehicle->restrictHistoryTo($user);

        return view('garage.vehicles.show', compact('vehicle', 'canAddMaintenance', 'canEdit', 'identifiersMasked', 'consignment'));
    }

    public function endConsignment(Request $request, Vehicle $vehicle, VehicleConsignmentService $consignments): RedirectResponse
    {
        Gate::authorize('endConsignment', $vehicle);

        $data = $request->validate([
            'end_reason' => ['required', 'in:sold,owner_withdrew'],
        ], [
            'end_reason.in' => 'Informe se o veículo foi vendido ou devolvido ao proprietário.',
        ]);

        $consignment = $consignments->activeFor($request->user(), $vehicle);

        if ($consignment === null) {
            return back()->withErrors(['consignment' => 'Este veículo não está em consignação nesta loja.']);
        }

        try {
            $consignments->end($consignment, $data['end_reason']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment' => $exception->getMessage()]);
        }

        return redirect()->route('garage.vehicles.index')
            ->with('success', 'Consignação encerrada. As manutenções que você registrou seguem no histórico do veículo.');
    }

    public function requestHistoryAccess(Request $request, Vehicle $vehicle, VehicleConsignmentService $consignments): RedirectResponse
    {
        Gate::authorize('endConsignment', $vehicle);

        $consignment = $consignments->activeFor($request->user(), $vehicle);

        if ($consignment === null) {
            return back()->withErrors(['consignment' => 'Este veículo não está em consignação nesta loja.']);
        }

        try {
            $consignments->requestHistoryAccess($consignment);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pedido enviado ao proprietário. Ele libera o histórico com um clique.');
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
