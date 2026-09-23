<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\CrlvImport;
use App\Models\Vehicle;
use App\Rules\Chassis;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Vehicle\VehicleOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $year = (int) request()->input('year', date('Y'));
        $chassisRule = Rule::unique('vehicles', 'chassis');
        if ($vehicleId) {
            $chassisRule = Rule::unique('vehicles', 'chassis')->ignore($vehicleId);
        }

        return [
            'license_plate' => ['required', 'string', 'max:10', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plateRule],
            'renavam' => ['required', 'digits:11', $renavamRule],
            'crv_number' => ['required', 'digits_between:10,12'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'chassis' => ['required', 'string', 'max:50', $chassisRule, new Chassis($year)],
            'motorization' => ['nullable', 'string', 'max:100'],
            'engine' => ['nullable', 'string', 'max:50'],
            'current_kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'terms_accepted' => ['required', 'accepted'],
            'crlv_verification_token' => ['nullable', 'string'],
        ];
    }

    /**
     * Só aceita o CRLV-e cujo token o formulário devolveu: garante que os
     * dados de propriedade vieram de um documento lido por este usuário.
     */
    protected function resolveCrlvFromSession(Request $request): ?CrlvParseResult
    {
        $import = $this->currentCrlvImport($request);

        if ($import === null) {
            return null;
        }

        $token = $request->input('crlv_verification_token');

        if ($token === null || ! hash_equals($import->token, (string) $token)) {
            return null;
        }

        return $import->toParseResult();
    }

    protected function registerVehicle(Request $request): RedirectResponse
    {
        $crlvPreview = $this->resolveCrlvFromSession($request);
        $chassisInput = Vehicle::normalizeChassis((string) $request->input('chassis', ''));
        if ($chassisInput === '' && $crlvPreview?->chassis) {
            $chassisInput = Vehicle::normalizeChassis($crlvPreview->chassis);
        }

        $request->merge([
            'license_plate' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('license_plate')) ?? ''),
            'renavam' => preg_replace('/\D/', '', (string) $request->input('renavam')) ?? '',
            'crv_number' => preg_replace('/\D/', '', (string) $request->input('crv_number')) ?? '',
            'chassis' => $chassisInput,
        ]);

        $data = $request->validate($this->vehicleValidationRules(), [
            'license_plate.regex' => 'Informe a placa no formato ABC1D23 ou ABC1234.',
            'renavam.digits' => 'O RENAVAM deve ter exatamente 11 dígitos.',
            'crv_number.digits_between' => 'O número do CRV deve ter entre 10 e 12 dígitos.',
            'year.min' => 'O ano do modelo deve ser no mínimo 1900.',
            'terms_accepted.required' => 'Role os termos de uso até o final e marque o aceite para continuar.',
            'terms_accepted.accepted' => 'Role os termos de uso até o final e marque o aceite para continuar.',
            'chassis.unique' => 'Já existe um veículo com este chassi. Você pode vinculá-lo em Vincular veículo.',
        ]);

        if (Vehicle::findByChassis($data['chassis'])) {
            return redirect()->route($this->vehicleClaimRoute())
                ->withInput(['chassis' => $data['chassis']])
                ->withErrors([
                    'chassis' => 'Já existe um veículo com este chassi. Você pode vinculá-lo em Vincular veículo.',
                ]);
        }

        if (Vehicle::findByRenavam($data['renavam'])) {
            return redirect()->route($this->vehicleClaimRoute())
                ->withInput(['renavam' => $data['renavam']])
                ->withErrors([
                    'renavam' => 'Este veículo já está cadastrado. Envie o CRLV-e para vincular à sua conta.',
                ]);
        }

        $import = $this->currentCrlvImport($request);
        $crlv = $this->resolveCrlvFromSession($request);
        $ownership = app(VehicleOwnershipService::class);

        try {
            if ($crlv !== null) {
                $ownershipType = $ownership->resolveOwnershipType($request->user(), $crlv);

                if ($ownershipType === 'consignment') {
                    $import?->forceFill([
                        'mode' => 'consignment',
                        'pending_vehicle_data' => $data,
                    ])->save();

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

        $this->finishCrlvImport($request, $import);

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

        $import = $this->currentCrlvImport($request);
        $vehicle = $import?->vehicle_id
            ? Vehicle::find($import->vehicle_id)
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
                $import->forceFill(['mode' => 'consignment'])->save();

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

        $this->finishCrlvImport($request, $import);

        return redirect()->route($this->vehicleShowRoute(), $vehicle)
            ->with('success', 'Veículo vinculado à sua conta com sucesso!');
    }

    /** Cadastro concluído: o documento lido não serve mais e sai da base. */
    protected function finishCrlvImport(Request $request, ?CrlvImport $import): void
    {
        $import?->markConsumed();
        $request->session()->forget('crlv_import_id');
    }

    abstract protected function vehicleShowRoute(): string;

    abstract protected function vehicleClaimRoute(): string;

    abstract protected function vehicleConsignmentRoute(): string;
}
