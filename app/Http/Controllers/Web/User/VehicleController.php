<?php

namespace App\Http\Controllers\Web\User;

use App\Enums\Portal;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AddsVehicleCovers;
use App\Http\Controllers\Web\Concerns\HandlesVehicleConsignment;
use App\Http\Controllers\Web\Concerns\ImportsVehicleFromCrlv;
use App\Http\Controllers\Web\Concerns\RegistersVehicleWithOwnership;
use App\Http\Controllers\Web\Concerns\UpdatesVehicleDetails;
use App\Jobs\EmailVehicleMaintenancePdf;
use App\Models\Vehicle;
use App\Rules\CrlvPdfFile;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use App\Services\VehicleCatalogService;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
     * Assistente "Adicionar veículo" (passos, rotas e textos do Proprietário).
     */
    protected function vehicleEntryFlow(): VehicleEntryFlow
    {
        return VehicleEntryFlow::for(Portal::Owner);
    }

    /**
     * Meus veículos: os de que a conta é dona atual (mesmo escopo de /api/v1/my-vehicles), com as
     * contagens e os pontos de procedência carregados de uma vez (x-vehicle.card não consulta por
     * card) e a próxima revisão estimada de cada um.
     */
    public function index(Request $request, VehicleMaintenanceReminderService $reminders): View
    {
        $vehicles = $request->user()->currentVehicles()
            ->with([
                'provenanceStripMaintenances',
                'maintenances' => fn ($query) => $query->select(['id', 'vehicle_id', 'kilometers']),
            ])
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
            ])
            ->orderByDesc('vehicles.created_at')
            ->paginate(24)
            ->withQueryString();

        $revisions = [];

        foreach ($vehicles as $vehicle) {
            $revisions[$vehicle->id] = $reminders->summarize($vehicle);
        }

        return view('user.vehicles.index', [
            'vehicles' => $vehicles,
            'revisions' => $revisions,
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
     * Ficha do veículo renderizada no servidor (x-vehicle.detail monta a linha do tempo, o histórico
     * e os documentos). As ações do cabeçalho seguem a VehiclePolicy. Chassi e RENAVAM inteiros (e
     * CRV e motor) só para o dono atual (VehiclePolicy::update), como na API; a conta que vê o
     * veículo por uma consignação aprovada recebe os números parciais.
     */
    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        $canEdit = Gate::allows('update', $vehicle);

        return view('user.vehicles.show', [
            'vehicle' => $vehicle,
            'canEdit' => $canEdit,
            'identifiersMasked' => ! $canEdit,
            'canAddMaintenance' => Gate::allows('addMaintenance', $vehicle),
            'canExportPdf' => Gate::allows('viewMaintenances', $vehicle),
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

        // Persiste na hora — o preenchimento só em formulário se perdia no ngrok. A placa nova entra
        // pelo histórico de placas (origem CRLV-e), como na edição manual.
        $crlvData = $parsed->toFormData();
        $crlvPlate = $crlvData['license_plate'] ?? null;
        unset($crlvData['license_plate']);

        $vehicle->update($crlvData);

        if (filled($crlvPlate)) {
            app(\App\Services\Vehicle\VehiclePlateHistoryService::class)->changePlate(
                $vehicle->fresh(),
                (string) $crlvPlate,
                'crlv_import',
                $request->user(),
            );
        }

        return redirect()
            ->route('user.vehicles.show', $vehicle)
            ->with(
                'success',
                'Dados do CRLV-e aplicados com sucesso. Número do CRV: '.$parsed->crvNumber.'.'
            );
    }

    /**
     * "Editar veículo": o mesmo contrato do Lojista (UpdatesVehicleDetails): chassi obrigatório,
     * quilometragem atual nunca abaixo da última manutenção e as duas capas recortadas no navegador.
     */
    public function update(Request $request, Vehicle $vehicle, VehicleCoverService $covers): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $this->updateVehicleDetails($request, $vehicle, $covers);

        return redirect()->route('user.vehicles.show', $vehicle)
            ->with('success', 'Veículo atualizado.');
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
