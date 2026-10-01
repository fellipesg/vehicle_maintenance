<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Vehicle\VehicleConsignmentService;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Support\AppStorage;
use App\Support\ContactMask;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Passo condicional do assistente de entrada de veículo: a consignação, quando o CRLV-e não está no
 * CPF/CNPJ da conta do lojista. A tela pede o contato do proprietário e a declaração de autorização,
 * e aceita a procuração como anexo opcional.
 *
 * A declaração basta para registrar manutenções: o lojista informa um serviço que ele mesmo pagou e
 * o proprietário é avisado de cada um. O histórico que o veículo já tinha é do dono — abre quando
 * ele libera pelo aviso que recebeu, ou quando a equipe aprova a procuração.
 *
 * Usa o CRLV-e lido guardado em crlv_verification e o que RegistersVehicleWithOwnership pôs em
 * consignment_pending.
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
            'contact' => $this->consignmentContext(
                $request,
                $preview ?? [],
                isset($pending['vehicle_id']) ? Vehicle::find($pending['vehicle_id']) : null,
            ),
        ]);
    }

    public function storeConsignment(Request $request): RedirectResponse
    {
        $flow = $this->vehicleEntryFlow();
        $pending = session('consignment_pending');

        if (! is_array($pending)) {
            return redirect()->route($flow->routeName('index'));
        }

        $existingVehicle = isset($pending['vehicle_id']) ? Vehicle::find($pending['vehicle_id']) : null;

        $declaration = $request->validate(
            $this->consignmentValidationRules($this->consignmentContactIsLocked($existingVehicle)),
            $this->consignmentValidationMessages(),
        );

        $preview = $this->pendingCrlvPreview($pending);

        if ($preview === null) {
            $this->forgetVehicleEntrySession($request);

            return redirect()->route($flow->routeName('create'))
                ->withErrors(['crlv' => 'A leitura do CRLV-e expirou. Envie o documento de novo.']);
        }

        $crlv = $this->crlvFromPreview($preview);
        $ownership = app(VehicleOwnershipService::class);
        $newVehicle = $existingVehicle === null;

        try {
            if ($newVehicle) {
                $vehicle = $ownership->registerNew($request->user(), $pending['vehicle_data'] ?? [], $crlv, 'consignment');
            } else {
                $vehicle = $existingVehicle;
                $ownership->attachConsignmentUser($request->user(), $vehicle, $crlv);
            }

            $consignment = $this->recordConsignment($request, $vehicle, $crlv, $declaration);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment_owner_name' => $exception->getMessage()]);
        }

        $this->forgetVehicleEntrySession($request);

        return redirect()->route($flow->routeName('index'))
            ->with('success', $this->consignmentSuccessMessage($consignment));
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
     * Dados do bloco do proprietário na tela do passo.
     *
     * O nome vem do CRLV-e que o próprio lojista enviou. Quando o veículo já é de uma conta da
     * RevisaLog, o contato dessa conta aparece só mascarado e nunca vai para o navegador: conferir
     * que é a mesma pessoa não pode entregar o e-mail dela ao lojista.
     *
     * @param  array<string, mixed>  $preview
     * @return array{
     *     owner_name: string|null,
     *     contact_locked: bool,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     * }
     */
    protected function consignmentContext(Request $request, array $preview, ?Vehicle $vehicle = null): array
    {
        $registeredOwner = $this->registeredOwnerOf($vehicle);

        return [
            'owner_name' => $registeredOwner?->name ?? ($preview['owner_name'] ?? null),
            'contact_locked' => $registeredOwner !== null,
            'masked_email' => ContactMask::email($registeredOwner?->email),
            'masked_phone' => ContactMask::phone($registeredOwner?->phone),
        ];
    }

    protected function consignmentContactIsLocked(?Vehicle $vehicle): bool
    {
        return $this->registeredOwnerOf($vehicle) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function consignmentValidationRules(bool $contactLocked): array
    {
        $rules = [
            'consignment_owner_name' => ['required', 'string', 'max:255'],
            'consignment_declaration' => ['required', 'accepted'],
            'power_of_attorney' => ['nullable', 'file', 'mimes:pdf', 'max:'.VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES],
        ];

        if ($contactLocked) {
            // O proprietário já tem conta: avisamos por ela e não pedimos — nem mostramos — o contato.
            return $rules;
        }

        return $rules + [
            'consignment_owner_email' => ['nullable', 'email', 'max:255', 'required_without:consignment_owner_phone'],
            'consignment_owner_phone' => ['nullable', 'string', 'max:30', 'required_without:consignment_owner_email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function consignmentValidationMessages(): array
    {
        return [
            'consignment_owner_name.required' => 'Informe o nome do proprietário do veículo.',
            'consignment_owner_email.required_without' => 'Informe e-mail ou telefone do proprietário para podermos avisá-lo.',
            'consignment_owner_phone.required_without' => 'Informe e-mail ou telefone do proprietário para podermos avisá-lo.',
            'consignment_declaration.required' => 'Confirme que você tem autorização do proprietário para registrar manutenções.',
            'consignment_declaration.accepted' => 'Confirme que você tem autorização do proprietário para registrar manutenções.',
            'power_of_attorney.mimes' => 'A procuração precisa ser um arquivo PDF.',
            'power_of_attorney.max' => 'A procuração pode ter no máximo 10 MB.',
        ];
    }

    /**
     * @param  array<string, mixed>  $declaration
     */
    protected function recordConsignment(
        Request $request,
        Vehicle $vehicle,
        CrlvParseResult $crlv,
        array $declaration,
    ): VehicleConsignment {
        $powerOfAttorneyPath = $request->hasFile('power_of_attorney')
            ? $request->file('power_of_attorney')->store('procuracoes', AppStorage::diskName())
            : null;

        return app(VehicleConsignmentService::class)->start(
            $request->user(),
            $vehicle,
            [
                'owner_name' => (string) $declaration['consignment_owner_name'],
                'owner_email' => $declaration['consignment_owner_email'] ?? null,
                'owner_phone' => $declaration['consignment_owner_phone'] ?? null,
                'owner_document' => $crlv->normalizedOwnerDocument(),
                'declaration_ip' => $request->ip(),
                'declaration_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
            $powerOfAttorneyPath,
        );
    }

    protected function consignmentSuccessMessage(VehicleConsignment $consignment): string
    {
        if ($consignment->isHistoryReviewPending()) {
            return 'Veículo em consignação adicionado. Você já pode registrar manutenções; a procuração foi enviada para análise e o histórico anterior abre depois dela.';
        }

        return 'Veículo em consignação adicionado. Você já pode registrar manutenções; o histórico anterior abre quando o proprietário liberar.';
    }

    private function registeredOwnerOf(?Vehicle $vehicle): ?User
    {
        if ($vehicle === null) {
            return null;
        }

        $owner = $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->first();

        return $owner !== null && ! $owner->isGarage() ? $owner : null;
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
