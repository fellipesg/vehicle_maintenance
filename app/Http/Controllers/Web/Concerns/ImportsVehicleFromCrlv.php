<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Rules\CrlvPdfFile;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvImportFailureReporter;
use App\Services\Crlv\CrlvParseException;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Services\VehicleCatalogService;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Passos 1 e 2 do assistente de entrada de veículo (App\Support\Vehicle\VehicleEntryFlow): a tela
 * Documento, a leitura do CRLV-e e a tela Conferir, que muda entre veículo novo e vínculo a um
 * veículo que já está na RevisaLog. Um upload só: a leitura decide o caminho.
 */
trait ImportsVehicleFromCrlv
{
    abstract protected function vehicleEntryFlow(): VehicleEntryFlow;

    /**
     * Passo 1, Documento: o CRLV-e como ação principal e o formulário manual recolhido.
     */
    public function create(Request $request, VehicleCatalogService $catalog): View
    {
        $flow = $this->vehicleEntryFlow();

        return view('vehicles.entry.document', [
            'flow' => $flow,
            'portal' => $flow->portal,
            'catalog' => $catalog->all(),
            'accountDocumentMissing' => $flow->isDealer() && $request->user()->normalizedDocument() === null,
            'vehicleExists' => (bool) session('vehicle_exists'),
        ]);
    }

    public function importCrlv(Request $request): RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();

        $validator = Validator::make($request->all(), [
            'crlv' => ['required', 'file', new CrlvPdfFile, 'max:'.VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES],
        ], [
            'crlv.required' => 'Escolha o PDF do CRLV-e para continuar.',
            'crlv.max' => 'O CRLV-e pode ter no máximo 10 MB.',
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route($flow->routeName('create')));
        }

        try {
            $parsed = app(CrlvPdfParser::class)->parseUpload($request->file('crlv'));
            app(CrlvExerciseValidator::class)->assertAcceptable($parsed->exerciseYear);
        } catch (RuntimeException $exception) {
            $this->reportCrlvFailure($request, $exception, $flow->routeName('create'));

            return redirect()->route($flow->routeName('create'))
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $existingVehicle = VehicleOwnershipService::findExistingVehicle($parsed);

        $this->putCrlvInSession($request, $parsed);

        if ($existingVehicle !== null) {
            $request->session()->put('claim_vehicle_id', $existingVehicle->id);

            return redirect()->route($flow->routeName('claim.preview'));
        }

        $request->session()->forget(['claim_vehicle_id', 'crlv_mode']);

        return redirect()->route($flow->routeName('import.preview'));
    }

    /**
     * Upload da antiga tela "Vincular": o passo Documento é um só, e a leitura já decide entre
     * veículo novo e vínculo. A rota continua respondendo para formulários abertos antes da troca.
     */
    public function importCrlvForClaim(Request $request): RedirectResponse
    {
        return $this->importCrlv($request);
    }

    /**
     * GET .../vincular (link antigo, favorito, "Reenviar procuração" do estoque): o vínculo começa no
     * mesmo passo Documento. Os avisos e erros da sessão seguem junto.
     */
    public function showClaimForm(Request $request): RedirectResponse
    {
        $request->session()->reflash();

        return redirect()->route($this->vehicleEntryFlow()->routeName('create'));
    }

    /**
     * Passo 2 para veículo novo: dados lidos do CRLV-e e o formulário para conferir e completar.
     */
    public function previewCrlvImport(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $preview = session('crlv_verification.parsed');

        if (! is_array($preview)) {
            return redirect()->route($flow->routeName('create'));
        }

        return view('vehicles.entry.review', [
            'flow' => $flow,
            'portal' => $flow->portal,
            'catalog' => $catalog->all(),
            'preview' => $preview,
            'sourceFile' => session('crlv_source'),
            'ownership' => $flow->ownershipFor($request->user(), $preview),
            'accountDocument' => $request->user()->document,
        ]);
    }

    /**
     * Passo 2 para veículo que já está na RevisaLog: o veículo encontrado, o histórico que vem junto
     * e a confirmação do vínculo.
     */
    public function previewCrlvClaim(Request $request): View|RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $preview = session('crlv_verification.parsed');
        $vehicleId = session('claim_vehicle_id');
        $vehicle = is_array($preview) && $vehicleId ? Vehicle::find($vehicleId) : null;

        if ($vehicle === null) {
            return redirect()->route($flow->routeName('create'));
        }

        $vehicle->loadCount([
            'maintenances',
            'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
        ]);

        return view('vehicles.entry.claim', [
            'flow' => $flow,
            'portal' => $flow->portal,
            'preview' => $preview,
            'vehicle' => $vehicle,
            'sourceFile' => session('crlv_source'),
            'ownership' => $flow->ownershipFor($request->user(), $preview, $vehicle),
            'accountDocument' => $request->user()->document,
            'alreadyLinked' => Gate::allows('update', $vehicle),
        ]);
    }

    /**
     * Documento que o leitor não entendeu vira alerta no Sentry e e-mail para
     * o suporte. Recusa por exercício vencido é erro de quem envia: não avisa.
     */
    protected function reportCrlvFailure(Request $request, RuntimeException $exception, string $origin): void
    {
        if (! $exception instanceof CrlvParseException) {
            return;
        }

        app(CrlvImportFailureReporter::class)->report(
            $exception,
            $request->file('crlv'),
            $request->user(),
            $origin,
        );
    }

    /**
     * Guarda o CRLV-e lido em uma cópia só: em produção a sessão viaja dentro
     * de um cookie, e o navegador descarta tudo acima de 4 KB sem avisar.
     */
    private function putCrlvInSession(Request $request, CrlvParseResult $parsed): void
    {
        $request->session()->put('crlv_verification', [
            'token' => $parsed->verificationToken(),
            'parsed' => $parsed->toPreview(),
        ]);
        $request->session()->put('crlv_source', $request->file('crlv')->getClientOriginalName());
    }
}
