<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Models\Vehicle;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Services\VehicleCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if (! session('consignment_pending')) {
            return redirect()->route('garage.vehicles.index');
        }

        return view('garage.vehicles.consignment', [
            'pending' => session('consignment_pending'),
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $pending = session('consignment_pending');

        if (! is_array($pending)) {
            return redirect()->route('garage.vehicles.index');
        }

        $request->validate([
            'power_of_attorney' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('power_of_attorney')->store('procuracoes', 'public');
        $crlv = $this->crlvFromVerification($pending['crlv_verification'] ?? session('crlv_verification'));
        $ownership = app(VehicleOwnershipService::class);

        try {
            if (isset($pending['vehicle_id'])) {
                $vehicle = Vehicle::findOrFail($pending['vehicle_id']);
                $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
                $ownership->attachConsignmentUser($request->user(), $vehicle, $crlv);
                $request->session()->forget(['consignment_pending', 'crlv_verification', 'crlv_preview', 'claim_vehicle_id']);

                return redirect()->route('garage.vehicles.show', $vehicle)
                    ->with('success', 'Procuração enviada para análise.');
            }

            $vehicle = $ownership->registerNew($request->user(), $pending['vehicle_data'], $crlv, 'consignment');
            $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
            $request->session()->forget(['consignment_pending', 'crlv_verification', 'crlv_preview']);

            return redirect()->route('garage.vehicles.show', $vehicle)
                ->with('success', 'Veículo adicionado em consignação.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['power_of_attorney' => $exception->getMessage()]);
        }
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load(['maintenances.items', 'maintenances.workshop']);

        return view('garage.vehicles.show', compact('vehicle'));
    }

    /**
     * @param  array<string, mixed>|null  $verification
     */
    private function crlvFromVerification(?array $verification): CrlvParseResult
    {
        $preview = $verification['parsed'] ?? null;

        if (! is_array($preview)) {
            throw new RuntimeException('Dados do CRLV-e não encontrados.');
        }

        return new CrlvParseResult(
            licensePlate: $preview['license_plate'],
            renavam: $preview['renavam'],
            brand: $preview['brand'],
            model: $preview['model'],
            year: (int) $preview['year'],
            crvNumber: $preview['crv_number'] ?? null,
            exerciseYear: isset($preview['exercise_year']) ? (int) $preview['exercise_year'] : null,
            ownerName: $preview['owner_name'] ?? null,
            ownerDocument: $preview['owner_document'] ?? null,
        );
    }
}
