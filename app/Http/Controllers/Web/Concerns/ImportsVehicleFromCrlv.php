<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\CrlvImport;
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

        $this->startCrlvImport($request, $parsed, $existingVehicle);

        if ($existingVehicle) {
            return redirect()->route($this->vehicleClaimPreviewRoute())
                ->with('info', 'Este veículo já está cadastrado. Confirme os dados do CRLV-e para vincular à sua conta.');
        }

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

        $this->startCrlvImport($request, $parsed, $existingVehicle);

        // Sem veículo na base, o CRLV-e já lido vale para o cadastro:
        // leva à mesma tela de confirmação, em vez de descartar a leitura.
        if ($existingVehicle === null) {
            return redirect()->route($this->vehiclePreviewRoute())
                ->with('info', 'Veículo ainda não cadastrado. Confirme os dados do CRLV-e para cadastrá-lo como primeiro proprietário.');
        }

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
     * O CRLV-e lido fica em `crlv_imports`; a sessão guarda só o id. Em
     * produção a sessão viaja dentro de um cookie, e o navegador descarta
     * tudo acima de 4 KB sem avisar.
     */
    private function startCrlvImport(
        Request $request,
        \App\Services\Crlv\CrlvParseResult $parsed,
        ?Vehicle $existingVehicle,
    ): CrlvImport {
        $import = CrlvImport::create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'token' => $parsed->verificationToken(),
            'mode' => $existingVehicle ? 'claim' : 'import',
            'vehicle_id' => $existingVehicle?->id,
            'parsed' => $parsed->toPreview(),
            'source_filename' => $request->file('crlv')->getClientOriginalName(),
            'expires_at' => now()->addMinutes(CrlvImport::LIFETIME_MINUTES),
        ]);

        $request->session()->put('crlv_import_id', $import->id);

        return $import;
    }

    /** Import em andamento desta sessão, se ainda valer e for de quem pediu. */
    protected function currentCrlvImport(Request $request): ?CrlvImport
    {
        $id = $request->session()->get('crlv_import_id');

        if (! is_string($id)) {
            return null;
        }

        return CrlvImport::query()
            ->usable()
            ->whereKey($id)
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->first();
    }

    public function previewCrlvImport(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null) {
            return redirect()->route($this->vehicleCreateRoute());
        }

        return view($this->vehiclePreviewView(), [
            'catalog' => $catalog->all(),
            'preview' => $import->parsed,
            'sourceFile' => $import->source_filename,
            'storeRoute' => $this->vehicleStoreRoute(),
            'createRoute' => $this->vehicleCreateRoute(),
        ]);
    }

    public function previewCrlvClaim(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null || $import->vehicle_id === null) {
            return redirect()->route($this->vehicleClaimRoute());
        }

        $preview = $import->parsed;
        $vehicle = Vehicle::find($import->vehicle_id);

        if ($vehicle === null) {
            return redirect()->route($this->vehicleClaimRoute());
        }

        return view($this->vehicleClaimPreviewView(), [
            'catalog' => $catalog->all(),
            'preview' => $preview,
            'vehicle' => $vehicle,
            'sourceFile' => $import->source_filename,
            'claimStoreRoute' => $this->vehicleClaimStoreRoute(),
            'claimRoute' => $this->vehicleClaimRoute(),
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
