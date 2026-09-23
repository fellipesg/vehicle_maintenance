<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Jobs\EmailVehicleMaintenancePdf;
use App\Models\Vehicle;
use App\Rules\CrlvPdfFile;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Services\VehicleCatalogService;
use App\Support\AppStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;
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
        return view('user.vehicles.index');
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
        $import = $this->currentCrlvImport($request);

        if ($import === null || $import->mode !== 'consignment') {
            return redirect()->route('user.vehicles.index');
        }

        return view('user.vehicles.consignment', [
            'pending' => $import,
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null || $import->mode !== 'consignment') {
            return redirect()->route('user.vehicles.index');
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

                return redirect()->route('user.vehicles.index')
                    ->with('success', 'Procuração enviada. O histórico ficará disponível após análise.');
            }

            $vehicle = $ownership->registerNew(
                $request->user(),
                $import->pending_vehicle_data ?? [],
                $crlv,
                'consignment',
            );
            $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
            $this->finishCrlvImport($request, $import);

            return redirect()->route('user.vehicles.index')
                ->with('success', 'Veículo cadastrado em consignação. A procuração será analisada pela equipe.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['power_of_attorney' => $exception->getMessage()]);
        }
    }

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        return view('user.vehicles.show', [
            'vehicleId' => $vehicle->id,
        ]);
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
            $this->reportCrlvFailure($request, $exception, 'user.vehicles.edit');

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

    public function update(Request $request, Vehicle $vehicle, VehicleCoverService $covers): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $chassisRules = ['nullable', 'string', 'max:50', \Illuminate\Validation\Rule::unique('vehicles', 'chassis')->ignore($vehicle->id), new \App\Rules\Chassis((int) $request->input('year', $vehicle->year))];
        if ($vehicle->chassis === null || $vehicle->chassis === '') {
            $chassisRules = ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('vehicles', 'chassis')->ignore($vehicle->id), new \App\Rules\Chassis((int) $request->input('year', $vehicle->year))];
        }

        $data = $request->validate([
            'license_plate' => ['required', 'string', 'max:10', 'unique:vehicles,license_plate,'.$vehicle->id],
            'renavam' => ['required', 'string', 'max:20', 'unique:vehicles,renavam,'.$vehicle->id],
            'crv_number' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis' => $chassisRules,
            'motorization' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:50'],
            'cover' => [
                'nullable',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
            'cover_portrait' => [
                'nullable',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ]);

        $cover = $request->file('cover');
        $coverPortrait = $request->file('cover_portrait');
        unset($data['cover'], $data['cover_portrait']);

        $previousPlate = $vehicle->license_plate;
        $newPlate = $data['license_plate'];
        unset($data['license_plate']);

        if (isset($data['chassis'])) {
            $data['chassis'] = \App\Models\Vehicle::normalizeChassis((string) $data['chassis']);
        }

        $vehicle->update($data);

        if (strtoupper((string) $newPlate) !== strtoupper((string) $previousPlate)) {
            app(\App\Services\Vehicle\VehiclePlateHistoryService::class)->changePlate(
                $vehicle->fresh(),
                $newPlate,
                'manual',
                $request->user(),
            );
        }

        if ($cover !== null) {
            $covers->storeLandscape($vehicle, $cover);
        }

        if ($coverPortrait !== null) {
            $covers->storePortrait($vehicle, $coverPortrait);
        }

        return redirect()->route('user.vehicles.show', $vehicle)
            ->with('success', 'Veículo atualizado com sucesso!');
    }

    public function exportPdf(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('viewMaintenances', $vehicle);

        $user = request()->user();

        EmailVehicleMaintenancePdf::dispatch($user, $vehicle);

        return back()->with(
            'success',
            "O relatório será processado e enviado para {$user->email}."
        );
    }
}
