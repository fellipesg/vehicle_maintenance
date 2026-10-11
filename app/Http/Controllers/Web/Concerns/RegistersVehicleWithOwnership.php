<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Rules\Chassis;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Maintenance\OwnerlessMaintenanceService;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Gravação do assistente de entrada de veículo (App\Support\Vehicle\VehicleEntryFlow): o veículo
 * novo (manual ou com o CRLV-e), o vínculo a um veículo existente e o desvio para a procuração
 * quando o lojista não é o proprietário do CRLV-e. Depois de gravar, o assistente segue para as capas.
 */
trait RegistersVehicleWithOwnership
{
    abstract protected function vehicleEntryFlow(): VehicleEntryFlow;

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
     * @return array<string, string>
     */
    protected function vehicleValidationMessages(): array
    {
        $flow = $this->vehicleEntryFlow();
        $termsMessage = 'Role os termos de uso até o final e marque o aceite para continuar.';

        return [
            'license_plate.regex' => 'Informe a placa no formato ABC1D23 ou ABC1234.',
            'license_plate.unique' => $flow->vehicleExistsMessage('esta placa'),
            'renavam.digits' => 'O RENAVAM deve ter exatamente 11 dígitos.',
            'renavam.unique' => $flow->vehicleExistsMessage('este RENAVAM'),
            'crv_number.digits_between' => 'O número do CRV deve ter entre 10 e 12 dígitos.',
            'year.min' => 'O ano do modelo deve ser no mínimo 1900.',
            'terms_accepted.required' => $termsMessage,
            'terms_accepted.accepted' => $termsMessage,
            'chassis.unique' => $flow->vehicleExistsMessage('este chassi'),
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

        return is_array($preview) ? $this->crlvFromPreview($preview) : null;
    }

    /**
     * Refaz o CRLV-e lido a partir da cópia guardada na sessão (CrlvParseResult::toPreview()).
     *
     * @param  array<string, mixed>  $preview
     */
    protected function crlvFromPreview(array $preview): CrlvParseResult
    {
        return new CrlvParseResult(
            licensePlate: (string) $preview['license_plate'],
            renavam: (string) $preview['renavam'],
            brand: (string) $preview['brand'],
            model: (string) $preview['model'],
            year: (int) $preview['year'],
            color: $preview['color'] ?? null,
            chassis: $preview['chassis'] ?? null,
            engine: $preview['engine'] ?? null,
            motorization: $preview['motorization'] ?? null,
            brandRaw: (string) ($preview['brand_raw'] ?? ''),
            modelRaw: (string) ($preview['model_raw'] ?? ''),
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
        $flow = $this->vehicleEntryFlow();
        $crlv = $this->resolveCrlvFromSession($request);
        $chassisInput = Vehicle::normalizeChassis((string) $request->input('chassis', ''));
        if ($chassisInput === '' && $crlv?->chassis) {
            $chassisInput = Vehicle::normalizeChassis($crlv->chassis);
        }

        $request->merge([
            'license_plate' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('license_plate')) ?? ''),
            'renavam' => preg_replace('/\D/', '', (string) $request->input('renavam')) ?? '',
            'crv_number' => preg_replace('/\D/', '', (string) $request->input('crv_number')) ?? '',
            'chassis' => $chassisInput,
        ]);

        if (! $flow->allowsManualEntry() && $crlv === null) {
            return back()->withInput()->withErrors([
                'crlv' => 'Envie o CRLV-e do veículo. No estoque o documento é obrigatório: é ele que identifica o proprietário e separa o veículo da loja do que está em consignação.',
            ]);
        }

        $existingVehicleRedirect = $this->redirectWhenVehicleExists($request);

        if ($existingVehicleRedirect !== null) {
            return $existingVehicleRedirect;
        }

        $data = $request->validate($this->vehicleValidationRules(), $this->vehicleValidationMessages());
        $ownership = app(VehicleOwnershipService::class);

        try {
            if ($crlv !== null && $ownership->resolveOwnershipType($request->user(), $crlv) === 'consignment') {
                return $this->startConsignment($request, [
                    'vehicle_data' => Arr::except($data, ['terms_accepted', 'crlv_verification_token']),
                ]);
            }

            $vehicle = $ownership->registerNew($request->user(), $data, $crlv);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['vehicle' => $exception->getMessage()]);
        }

        $request->session()->forget(['crlv_verification', 'crlv_source']);

        return redirect()->route($flow->routeName('covers'), $vehicle)
            ->with('success', $flow->addedMessage($crlv !== null));
    }

    protected function claimVehicle(Request $request): RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $expiredMessage = 'A leitura do CRLV-e expirou. Envie o documento de novo para vincular o veículo.';

        $request->validate([
            'crlv_verification_token' => ['required', 'string'],
        ], [
            'crlv_verification_token.required' => $expiredMessage,
        ]);

        $vehicle = session('claim_vehicle_id')
            ? Vehicle::find(session('claim_vehicle_id'))
            : null;

        $crlv = $this->resolveCrlvFromSession($request);

        if ($vehicle === null || $crlv === null) {
            return redirect()->route($flow->routeName('create'))
                ->withErrors(['crlv' => $expiredMessage]);
        }

        $ownership = app(VehicleOwnershipService::class);

        try {
            if ($ownership->resolveClaimOwnershipType($request->user(), $vehicle, $crlv) === 'consignment') {
                return $this->startConsignment($request, ['vehicle_id' => $vehicle->id]);
            }

            $vehicle = $ownership->claimExisting($request->user(), $vehicle, $crlv);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'consignment_required') {
                return $this->startConsignment($request, ['vehicle_id' => $vehicle->id]);
            }

            return back()->withErrors(['vehicle' => $exception->getMessage()]);
        }

        $request->session()->forget(['crlv_verification', 'crlv_source', 'claim_vehicle_id', 'crlv_mode', 'invite_notice']);

        // Chegou a um carro com registros de oficina: a escolha do que entra no histórico vem primeiro.
        if (! $request->user()->isGarage()
            && app(OwnerlessMaintenanceService::class)->pendingCountForVehicle($vehicle) > 0) {
            return redirect()->route('user.workshop-records.index')
                ->with('success', 'Veículo vinculado. Oficinas registraram serviços nele: escolha abaixo o que entra no seu histórico.');
        }

        return redirect()->route($flow->routeName('covers'), $vehicle)
            ->with('success', $flow->claimedMessage());
    }

    /**
     * Chassi ou RENAVAM que já está na RevisaLog não vira um segundo cadastro: volta ao passo
     * Documento, que pede o CRLV-e para o vínculo (a leitura encontra o cadastro sozinha).
     */
    private function redirectWhenVehicleExists(Request $request): ?RedirectResponse
    {
        $chassis = (string) $request->input('chassis');
        $renavam = (string) $request->input('renavam');

        $field = match (true) {
            $chassis !== '' && Vehicle::findByChassis($chassis) !== null => 'chassis',
            $renavam !== '' && Vehicle::findByRenavam($renavam) !== null => 'renavam',
            default => null,
        };

        if ($field === null) {
            return null;
        }

        $flow = $this->vehicleEntryFlow();

        return redirect()->route($flow->routeName('create'))
            ->withInput($request->except(['terms_accepted', 'crlv_verification_token']))
            ->withErrors([$field => $flow->vehicleExistsMessage($field === 'chassis' ? 'este chassi' : 'este RENAVAM')])
            ->with('vehicle_exists', true);
    }

    /**
     * Desvio para a procuração (consignação). O CRLV-e lido continua só em crlv_verification: a
     * sessão viaja num cookie de 4 KB e não comporta uma segunda cópia.
     *
     * @param  array{vehicle_id?: int, vehicle_data?: array<string, mixed>}  $pending
     */
    private function startConsignment(Request $request, array $pending): RedirectResponse
    {
        $request->session()->put('consignment_pending', $pending + [
            'reason' => VehicleEntryFlow::consignmentReasonFor($request->user()),
        ]);

        return redirect()->route($this->vehicleEntryFlow()->routeName('consignment'));
    }
}
