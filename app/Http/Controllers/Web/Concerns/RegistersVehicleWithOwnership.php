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
            'license_plate' => ['required', 'string', 'max:10', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plateRule],
            'renavam' => ['required', 'digits:11', $renavamRule],
            'crv_number' => ['required', 'digits_between:10,12'],
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
        $request->merge([
            'license_plate' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('license_plate')) ?? ''),
            'renavam' => preg_replace('/\D/', '', (string) $request->input('renavam')) ?? '',
            'crv_number' => preg_replace('/\D/', '', (string) $request->input('crv_number')) ?? '',
        ]);

        $data = $request->validate($this->vehicleValidationRules(), [
            'license_plate.regex' => 'Informe a placa no formato ABC1D23 ou ABC1234.',
            'renavam.digits' => 'O RENAVAM deve ter exatamente 11 dígitos.',
            'crv_number.digits_between' => 'O número do CRV deve ter entre 10 e 12 dígitos.',
            'year.min' => 'O ano do modelo deve ser no mínimo 1900.',
        ]);

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
            if ($crlv !== null) {
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
            } else {
                $vehicle = $ownership->registerNew($request->user(), $data, null);
            }
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['vehicle' => $exception->getMessage()]);
        }

        $request->session()->forget(['crlv_verification', 'crlv_preview', 'crlv_source']);

        $successMessage = $crlv !== null
            ? 'Veículo cadastrado com sucesso!'
            : 'Veículo cadastrado com sucesso! Você pode importar o CRLV-e depois para validar a propriedade.';

        return redirect()->route($this->vehicleShowRoute(), $vehicle)
            ->with('success', $successMessage);
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
