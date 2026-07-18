<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Vehicle\VehicleOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

trait RegistersVehicleWithOwnership
{
    /**
     * @return array<string, mixed>
     */
    protected function vehicleValidationRules(?int $vehicleId = null): array
    {
        $plateRule = 'unique:vehicles,license_plate';
        $renavamRule = 'unique:vehicles,renavam';

        if ($vehicleId) {
            $plateRule .= ','.$vehicleId;
            $renavamRule .= ','.$vehicleId;
        }

        return [
            'license_plate' => ['required', 'string', 'max:10', $plateRule],
            'renavam' => ['required', 'string', 'max:20', $renavamRule],
            'crv_number' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis' => ['nullable', 'string', 'max:50'],
            'motorization' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:50'],
            'crlv_verification_token' => ['nullable', 'string'],
        ];
    }

    protected function resolveCrlvFromSession(Request $request): ?CrlvParseResult
    {
        $verification = session('crlv_verification');

        if (! is_array($verification)) {
            return null;
        }

        $token = $request->input('crlv_verification_token');

        if ($token === null || $token !== ($verification['token'] ?? null)) {
            return null;
        }

        $preview = $verification['parsed'] ?? null;

        if (! is_array($preview)) {
            return null;
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
            brandRaw: $preview['brand_raw'] ?? '',
            modelRaw: $preview['model_raw'] ?? '',
            brandMatched: (bool) ($preview['brand_matched'] ?? false),
            modelMatched: (bool) ($preview['model_matched'] ?? false),
            detranState: $preview['detran_state'] ?? null,
            fuel: $preview['fuel'] ?? null,
            crvNumber: $preview['crv_number'] ?? null,
            exerciseYear: isset($preview['exercise_year']) ? (int) $preview['exercise_year'] : null,
            manufacturingYear: isset($preview['manufacturing_year']) ? (int) $preview['manufacturing_year'] : null,
            ownerName: $preview['owner_name'] ?? null,
            ownerDocument: $preview['owner_document'] ?? null,
        );
    }

    protected function registerVehicle(Request $request): RedirectResponse
    {
        $data = $request->validate($this->vehicleValidationRules());

        if (Vehicle::findByRenavam($data['renavam'])) {
            return redirect()->route($this->vehicleClaimRoute())
                ->withInput(['renavam' => $data['renavam']])
                ->withErrors([
                    'renavam' => 'Este veículo já está cadastrado. Envie o CRLV-e para vincular à sua conta.',
                ]);
        }

        $crlv = $this->resolveCrlvFromSession($request);
        $ownership = app(VehicleOwnershipService::class);

        try {
            if ($crlv === null) {
                return back()->withInput()->withErrors([
                    'crlv' => 'Para cadastrar um veículo novo, importe o CRLV-e digital ou vincule um veículo já existente.',
                ]);
            }

            $ownershipType = $ownership->resolveOwnershipType($request->user(), $crlv);

            if ($ownershipType === 'consignment') {
                session([
                    'consignment_pending' => [
                        'vehicle_data' => $data,
                        'crlv_verification' => session('crlv_verification'),
                    ],
                ]);

                return redirect()->route($this->vehicleConsignmentRoute())
                    ->with('warning', 'O CPF/CNPJ do CRLV-e não é o seu. Envie a procuração do proprietário para acessar o histórico em consignação.');
            }

            $vehicle = $ownership->registerNew($request->user(), $data, $crlv);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['vehicle' => $exception->getMessage()]);
        }

        $request->session()->forget(['crlv_verification', 'crlv_preview', 'crlv_source']);

        return redirect()->route($this->vehicleShowRoute(), $vehicle)
            ->with('success', 'Veículo cadastrado com sucesso!');
    }

    protected function claimVehicle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'crlv_verification_token' => ['required', 'string'],
        ]);

        $vehicle = session('claim_vehicle_id')
            ? Vehicle::find(session('claim_vehicle_id'))
            : null;

        $crlv = $this->resolveCrlvFromSession($request);

        if ($vehicle === null || $crlv === null) {
            return redirect()->route($this->vehicleClaimRoute())
                ->withErrors(['crlv' => 'Sessão de vinculação expirada. Envie o CRLV-e novamente.']);
        }

        $ownership = app(VehicleOwnershipService::class);

        try {
            $ownershipType = $ownership->resolveOwnershipType($request->user(), $crlv);

            if ($ownershipType === 'consignment') {
                session([
                    'consignment_pending' => [
                        'vehicle_id' => $vehicle->id,
                        'crlv_verification' => session('crlv_verification'),
                    ],
                ]);

                return redirect()->route($this->vehicleConsignmentRoute())
                    ->with('warning', 'O veículo não está no seu CPF/CNPJ. Envie a procuração do proprietário para acessar o histórico em consignação.');
            }

            $vehicle = $ownership->claimExisting($request->user(), $vehicle, $crlv);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'consignment_required') {
                return redirect()->route($this->vehicleConsignmentRoute());
            }

            return back()->withErrors(['vehicle' => $exception->getMessage()]);
        }

        $request->session()->forget(['crlv_verification', 'crlv_preview', 'crlv_source', 'claim_vehicle_id', 'crlv_mode']);

        return redirect()->route($this->vehicleShowRoute(), $vehicle)
            ->with('success', 'Veículo vinculado à sua conta com sucesso!');
    }

    abstract protected function vehicleShowRoute(): string;

    abstract protected function vehicleClaimRoute(): string;

    abstract protected function vehicleConsignmentRoute(): string;
}
