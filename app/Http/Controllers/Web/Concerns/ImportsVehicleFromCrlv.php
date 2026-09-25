<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Rules\CrlvPdfFile;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\VehicleCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

trait ImportsVehicleFromCrlv
{
    abstract protected function vehicleCreateRoute(): string;

    abstract protected function vehiclePreviewRoute(): string;

    abstract protected function vehicleClaimPreviewRoute(): string;

    abstract protected function vehicleStoreRoute(): string;

    abstract protected function vehicleClaimStoreRoute(): string;

    abstract protected function vehiclePreviewView(): string;

    abstract protected function vehicleClaimPreviewView(): string;

    abstract protected function vehicleClaimRoute(): string;

    public function importCrlv(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'crlv' => ['required', 'file', new CrlvPdfFile, 'max:10240'],
        ], [
            'crlv.max' => 'O CRLV-e pode ter no máximo 10 MB.',
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route($this->vehicleCreateRoute()));
        }

        try {
            $parsed = app(CrlvPdfParser::class)->parseUpload($request->file('crlv'));
            app(\App\Services\Crlv\CrlvExerciseValidator::class)->assertAcceptable($parsed->exerciseYear);
        } catch (RuntimeException $exception) {
            $this->reportCrlvFailure($request, $exception, $this->vehicleCreateRoute());

            return redirect()->route($this->vehicleCreateRoute())
                ->withInput()
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $existingVehicle = \App\Services\Vehicle\VehicleOwnershipService::findExistingVehicle($parsed);

        $this->putCrlvInSession($request, $parsed);

        if ($existingVehicle) {
            $request->session()->put('claim_vehicle_id', $existingVehicle->id);
            $request->session()->put('crlv_mode', 'claim');

            return redirect()->route($this->vehicleClaimPreviewRoute())
                ->with('info', 'Este veículo já está cadastrado. Confirme os dados do CRLV-e para vincular à sua conta.');
        }

        $request->session()->forget(['claim_vehicle_id', 'crlv_mode']);

        return redirect()->route($this->vehiclePreviewRoute());
    }

    public function importCrlvForClaim(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'crlv' => ['required', 'file', new CrlvPdfFile, 'max:10240'],
        ], [
            'crlv.max' => 'O CRLV-e pode ter no máximo 10 MB.',
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route($this->vehicleClaimRoute()));
        }

        try {
            $parsed = app(CrlvPdfParser::class)->parseUpload($request->file('crlv'));
            app(\App\Services\Crlv\CrlvExerciseValidator::class)->assertAcceptable($parsed->exerciseYear);
        } catch (RuntimeException $exception) {
            $this->reportCrlvFailure($request, $exception, $this->vehicleClaimRoute());

            return redirect()->route($this->vehicleClaimRoute())
                ->withInput()
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $existingVehicle = \App\Services\Vehicle\VehicleOwnershipService::findExistingVehicle($parsed);

        $this->putCrlvInSession($request, $parsed);

        // Sem veículo na base, o CRLV-e já lido vale para o cadastro:
        // leva à mesma tela de confirmação, em vez de descartar a leitura.
        if ($existingVehicle === null) {
            $request->session()->forget(['claim_vehicle_id', 'crlv_mode']);

            return redirect()->route($this->vehiclePreviewRoute())
                ->with('info', 'Veículo ainda não cadastrado. Confirme os dados do CRLV-e para cadastrá-lo como primeiro proprietário.');
        }

        $request->session()->put('claim_vehicle_id', $existingVehicle->id);
        $request->session()->put('crlv_mode', 'claim');

        return redirect()->route($this->vehicleClaimPreviewRoute());
    }

    /**
     * Documento que o leitor não entendeu vira alerta no Sentry e e-mail para
     * o suporte. Recusa por exercício vencido é erro de quem envia: não avisa.
     */
    protected function reportCrlvFailure(Request $request, RuntimeException $exception, string $origin): void
    {
        if (! $exception instanceof \App\Services\Crlv\CrlvParseException) {
            return;
        }

        app(\App\Services\Crlv\CrlvImportFailureReporter::class)->report(
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
    private function putCrlvInSession(Request $request, \App\Services\Crlv\CrlvParseResult $parsed): void
    {
        $request->session()->put('crlv_verification', [
            'token' => $parsed->verificationToken(),
            'parsed' => $parsed->toPreview(),
        ]);
        $request->session()->put('crlv_source', $request->file('crlv')->getClientOriginalName());
    }

    public function previewCrlvImport(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $preview = session('crlv_verification.parsed');

        if (! is_array($preview)) {
            return redirect()->route($this->vehicleCreateRoute());
        }

        return view($this->vehiclePreviewView(), [
            'catalog' => $catalog->all(),
            'preview' => $preview,
            'sourceFile' => session('crlv_source'),
            'storeRoute' => $this->vehicleStoreRoute(),
            'createRoute' => $this->vehicleCreateRoute(),
            'consignment' => $this->consignmentContext($request, $preview),
        ]);
    }

    public function previewCrlvClaim(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $preview = session('crlv_verification.parsed');
        $vehicleId = session('claim_vehicle_id');

        if (! is_array($preview) || ! $vehicleId) {
            return redirect()->route($this->vehicleClaimRoute());
        }

        $vehicle = Vehicle::find($vehicleId);

        if ($vehicle === null) {
            return redirect()->route($this->vehicleClaimRoute());
        }

        return view($this->vehicleClaimPreviewView(), [
            'catalog' => $catalog->all(),
            'preview' => $preview,
            'vehicle' => $vehicle,
            'sourceFile' => session('crlv_source'),
            'claimStoreRoute' => $this->vehicleClaimStoreRoute(),
            'claimRoute' => $this->vehicleClaimRoute(),
            'consignment' => $this->consignmentContext($request, $preview, $vehicle),
        ]);
    }

    public function showClaimForm(): View
    {
        return view($this->vehicleClaimView(), [
            'claimImportRoute' => route($this->vehicleClaimImportRoute()),
            'createRoute' => $this->vehicleCreateRoute(),
        ]);
    }

    abstract protected function vehicleClaimView(): string;

    abstract protected function vehicleClaimImportRoute(): string;
}
