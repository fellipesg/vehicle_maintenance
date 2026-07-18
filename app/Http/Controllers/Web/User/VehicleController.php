<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Models\Vehicle;
use App\Rules\CrlvPdfFile;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Services\VehicleCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class VehicleController extends Controller
{
    use ImportsVehicleFromCrlv;
    use RegistersVehicleWithOwnership;

    protected function vehicleCreateRoute(): string
    {
        return 'user.vehicles.create';
    }

    protected function vehiclePreviewRoute(): string
    {
        return 'user.vehicles.import.preview';
    }

    protected function vehicleClaimPreviewRoute(): string
    {
        return 'user.vehicles.claim.preview';
    }

    protected function vehicleStoreRoute(): string
    {
        return 'user.vehicles.store';
    }

    protected function vehicleClaimStoreRoute(): string
    {
        return 'user.vehicles.claim.store';
    }

    protected function vehiclePreviewView(): string
    {
        return 'user.vehicles.preview-import';
    }

    protected function vehicleClaimPreviewView(): string
    {
        return 'user.vehicles.preview-claim';
    }

    protected function vehicleClaimView(): string
    {
        return 'user.vehicles.claim';
    }

    protected function vehicleClaimImportRoute(): string
    {
        return 'user.vehicles.claim.import-crlv';
    }

    protected function vehicleShowRoute(): string
    {
        return 'user.vehicles.show';
    }

    protected function vehicleClaimRoute(): string
    {
        return 'user.vehicles.claim';
    }

    protected function vehicleConsignmentRoute(): string
    {
        return 'user.vehicles.consignment';
    }

    public function index(Request $request): View
    {
        $vehicles = $request->user()->currentVehicles()
            ->withCount('maintenances')
            ->get();

        return view('user.vehicles.index', compact('vehicles'));
    }

    public function create(VehicleCatalogService $catalog): View
    {
        return view('user.vehicles.create', [
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
            return redirect()->route('user.vehicles.index');
        }

        return view('user.vehicles.consignment', [
            'pending' => session('consignment_pending'),
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $pending = session('consignment_pending');

        if (! is_array($pending)) {
            return redirect()->route('user.vehicles.index');
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

                return redirect()->route('user.vehicles.show', $vehicle)
                    ->with('success', 'Procuração enviada. O histórico ficará disponível após análise.');
            }

            $vehicle = $ownership->registerNew(
                $request->user(),
                $pending['vehicle_data'],
                $crlv,
                'consignment',
            );
            $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
            $request->session()->forget(['consignment_pending', 'crlv_verification', 'crlv_preview']);

            return redirect()->route('user.vehicles.show', $vehicle)
                ->with('success', 'Veículo cadastrado em consignação. A procuração será analisada pela equipe.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['power_of_attorney' => $exception->getMessage()]);
        }
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load(['maintenances.items', 'maintenances.invoices', 'maintenances.workshop']);

        $canViewMaintenances = auth()->user()->canViewVehicleMaintenances($vehicle);

        return view('user.vehicles.show', compact('vehicle', 'canViewMaintenances'));
    }

    public function edit(Vehicle $vehicle, VehicleCatalogService $catalog): View
    {
        Gate::authorize('update', $vehicle);

        return view('user.vehicles.edit', [
            'vehicle' => $vehicle,
            'catalog' => $catalog->all(),
        ]);
    }

    public function importCrlvForEdit(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $validator = Validator::make($request->all(), [
            'crlv' => ['required', 'file', new CrlvPdfFile, 'max:10240'],
        ], [
            'crlv.max' => 'O CRLV-e pode ter no máximo 10 MB.',
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route('user.vehicles.edit', $vehicle));
        }

        try {
            $parsed = app(CrlvPdfParser::class)->parseUpload($request->file('crlv'));
            app(CrlvExerciseValidator::class)->assertAcceptable($parsed->exerciseYear);
        } catch (RuntimeException $exception) {
            return redirect()->route('user.vehicles.edit', $vehicle)
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $vehicleRenavam = preg_replace('/\D/', '', $vehicle->renavam) ?? '';
        $crlvRenavam = $parsed->normalizedRenavam();

        if ($vehicleRenavam !== $crlvRenavam) {
            return redirect()->route('user.vehicles.edit', $vehicle)
                ->withErrors([
                    'crlv' => 'O RENAVAM do CRLV-e ('.$parsed->renavam.') não confere com este veículo ('.$vehicle->renavam.').',
                ]);
        }

        // Persiste na hora — o preenchimento só em formulário se perdia no ngrok.
        $vehicle->update($parsed->toFormData());

        return redirect()
            ->route('user.vehicles.show', $vehicle)
            ->with(
                'success',
                'Dados do CRLV-e aplicados com sucesso. Número do CRV: '.$parsed->crvNumber.'.'
            );
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $data = $request->validate([
            'license_plate' => ['required', 'string', 'max:10', 'unique:vehicles,license_plate,'.$vehicle->id],
            'renavam' => ['required', 'string', 'max:20', 'unique:vehicles,renavam,'.$vehicle->id],
            'crv_number' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis' => ['nullable', 'string', 'max:50'],
            'motorization' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:50'],
        ]);

        $vehicle->update($data);

        return redirect()->route('user.vehicles.show', $vehicle)
            ->with('success', 'Veículo atualizado com sucesso!');
    }

    public function exportPdf(Vehicle $vehicle)
    {
        return app(\App\Http\Controllers\Api\VehicleController::class)
            ->exportPdf(request(), (string) $vehicle->id);
    }

    /**
     * @param  array<string, mixed>|null  $verification
     */
    private function crlvFromVerification(?array $verification): CrlvParseResult
    {
        $preview = $verification['parsed'] ?? null;

        if (! is_array($preview)) {
            throw new RuntimeException('Dados do CRLV-e não encontrados. Envie o documento novamente.');
        }

        return new CrlvParseResult(
            licensePlate: $preview['license_plate'],
            renavam: $preview['renavam'],
            brand: $preview['brand'],
            model: $preview['model'],
            year: (int) $preview['year'],
            color: $preview['color'] ?? null,
            chassis: $preview['chassis'] ?? null,
            engine: $preview['engine'] ?? null,
            motorization: $preview['motorization'] ?? null,
            crvNumber: $preview['crv_number'] ?? null,
            exerciseYear: isset($preview['exercise_year']) ? (int) $preview['exercise_year'] : null,
            manufacturingYear: isset($preview['manufacturing_year']) ? (int) $preview['manufacturing_year'] : null,
            ownerName: $preview['owner_name'] ?? null,
            ownerDocument: $preview['owner_document'] ?? null,
        );
    }
}
