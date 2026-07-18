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
            return redirect()->route($this->vehicleCreateRoute())
                ->withInput()
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $existingVehicle = Vehicle::findByRenavam($parsed->renavam);

        $request->session()->put('crlv_verification', [
            'token' => $parsed->verificationToken(),
            'parsed' => $parsed->toPreview(),
        ]);
        $request->session()->put('crlv_preview', $parsed->toPreview());
        $request->session()->put('crlv_source', $request->file('crlv')->getClientOriginalName());

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
            return redirect()->route($this->vehicleClaimRoute())
                ->withInput()
                ->withErrors(['crlv' => $exception->getMessage()]);
        }

        $existingVehicle = Vehicle::findByRenavam($parsed->renavam);

        if ($existingVehicle === null) {
            return redirect()->route($this->vehicleCreateRoute())
                ->with('info', 'Veículo não encontrado. Você pode cadastrá-lo como primeiro proprietário.')
                ->with('crlv_verification', [
                    'token' => $parsed->verificationToken(),
                    'parsed' => $parsed->toPreview(),
                ])
                ->with('crlv_preview', $parsed->toPreview())
                ->with('crlv_source', $request->file('crlv')->getClientOriginalName());
        }

        $request->session()->put('crlv_verification', [
            'token' => $parsed->verificationToken(),
            'parsed' => $parsed->toPreview(),
        ]);
        $request->session()->put('crlv_preview', $parsed->toPreview());
        $request->session()->put('crlv_source', $request->file('crlv')->getClientOriginalName());
        $request->session()->put('claim_vehicle_id', $existingVehicle->id);
        $request->session()->put('crlv_mode', 'claim');

        return redirect()->route($this->vehicleClaimPreviewRoute());
    }

    public function previewCrlvImport(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $preview = session('crlv_preview');

        if (! is_array($preview)) {
            return redirect()->route($this->vehicleCreateRoute());
        }

        return view($this->vehiclePreviewView(), [
            'catalog' => $catalog->all(),
            'preview' => $preview,
            'sourceFile' => session('crlv_source'),
            'storeRoute' => $this->vehicleStoreRoute(),
            'createRoute' => $this->vehicleCreateRoute(),
        ]);
    }

    public function previewCrlvClaim(Request $request, VehicleCatalogService $catalog): View|RedirectResponse
    {
        $preview = session('crlv_preview');
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
