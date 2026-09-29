<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Support\AppStorage;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Passo condicional do assistente de entrada de veículo: a procuração, quando o CRLV-e não está no
 * CPF/CNPJ da conta do lojista (consignação). A tela mostra o veículo, o motivo, como a análise
 * funciona e deixa cancelar; depois do envio, o status fica no estoque. Usa o CRLV-e lido guardado
 * em crlv_verification e o que RegistersVehicleWithOwnership pôs em consignment_pending.
 */
trait HandlesVehicleConsignment
{
    abstract protected function vehicleEntryFlow(): VehicleEntryFlow;

    /**
     * @param  array<string, mixed>  $preview
     */
    abstract protected function crlvFromPreview(array $preview): CrlvParseResult;

    public function showConsignmentForm(Request $request): View|RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $pending = session('consignment_pending');

        if (! is_array($pending)) {
            return redirect()->route($flow->routeName('index'));
        }

        $preview = $this->pendingCrlvPreview($pending);

        return view('vehicles.entry.power-of-attorney', [
            'flow' => $flow,
            'portal' => $flow->portal,
            'vehicle' => isset($pending['vehicle_id']) ? Vehicle::find($pending['vehicle_id']) : null,
            'vehicleData' => is_array($pending['vehicle_data'] ?? null) ? $pending['vehicle_data'] : null,
            'preview' => $preview,
            'reason' => $pending['reason'] ?? VehicleEntryFlow::consignmentReasonFor($request->user()),
            'accountDocument' => $request->user()->document,
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $pending = session('consignment_pending');

        if (! is_array($pending)) {
            return redirect()->route($flow->routeName('index'));
        }

        $request->validate([
            'power_of_attorney' => ['required', 'file', 'mimes:pdf', 'max:'.VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES],
        ], [
            'power_of_attorney.required' => 'Escolha o PDF da procuração para enviar.',
            'power_of_attorney.mimes' => 'A procuração precisa ser um arquivo PDF.',
            'power_of_attorney.max' => 'A procuração pode ter no máximo 10 MB.',
        ]);

        $preview = $this->pendingCrlvPreview($pending);

        if ($preview === null) {
            $this->forgetVehicleEntrySession($request);

            return redirect()->route($flow->routeName('create'))
                ->withErrors(['crlv' => 'A leitura do CRLV-e expirou. Envie o documento de novo.']);
        }

        $crlv = $this->crlvFromPreview($preview);
        $path = $request->file('power_of_attorney')->store('procuracoes', AppStorage::diskName());
        $ownership = app(VehicleOwnershipService::class);
        $newVehicle = ! isset($pending['vehicle_id']);

        try {
            if ($newVehicle) {
                $vehicle = $ownership->registerNew($request->user(), $pending['vehicle_data'] ?? [], $crlv, 'consignment');
                $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
            } else {
                $vehicle = Vehicle::findOrFail($pending['vehicle_id']);
                $ownership->requestConsignmentAccess($request->user(), $vehicle, $crlv, $path);
                $ownership->attachConsignmentUser($request->user(), $vehicle, $crlv);
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['power_of_attorney' => $exception->getMessage()]);
        }

        $this->forgetVehicleEntrySession($request);

        return redirect()->route($flow->routeName('index'))
            ->with('success', $flow->consignmentSentMessage($newVehicle));
    }

    /**
     * "Cancelar" no passo da procuração: descarta o CRLV-e lido e volta ao passo Documento. Nada foi
     * gravado ainda (o veículo e o vínculo só nascem no envio da procuração).
     */
    public function cancelConsignment(Request $request): RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();

        $this->forgetVehicleEntrySession($request);

        return redirect()->route($flow->routeName('create'))
            ->with('info', $flow->consignmentCancelledMessage());
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>|null
     */
    private function pendingCrlvPreview(array $pending): ?array
    {
        $preview = $pending['crlv_verification']['parsed'] ?? session('crlv_verification.parsed');

        return is_array($preview) ? $preview : null;
    }

    private function forgetVehicleEntrySession(Request $request): void
    {
        $request->session()->forget(['consignment_pending', 'crlv_verification', 'crlv_source', 'claim_vehicle_id', 'crlv_mode']);
    }
}
