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
use Illuminate\Http\Request;

/**
 * Collects the consignment declaration a garage fills in when the vehicle it is
 * registering belongs to someone else.
 */
trait HandlesVehicleConsignment
{
    /**
     * A garage is consigning when it says so, or when the CRLV-e it uploaded is in
     * someone else's name — the second case wins, so the switch cannot be unticked to
     * pass a third party's car off as the garage's own.
     */
    protected function isConsignmentFlow(Request $request, ?CrlvParseResult $crlv): bool
    {
        $user = $request->user();

        if (! $user->isGarage()) {
            return false;
        }

        if (app(VehicleOwnershipService::class)->resolveOwnershipType($user, $crlv) === 'consignment') {
            return true;
        }

        return $request->boolean('is_consignment');
    }

    /**
     * Everything the consignment block on the CRLV preview screens needs.
     *
     * The owner's name is pre-filled from the CRLV-e the garage itself uploaded. When the
     * vehicle already belongs to a registered user, their contact is only ever rendered
     * masked and never sent to the browser as a value the garage could read back.
     *
     * @param  array<string, mixed>  $preview
     * @return array{
     *     available: bool,
     *     required: bool,
     *     owner_name: string|null,
     *     owner_email: string|null,
     *     owner_phone: string|null,
     *     contact_locked: bool,
     *     masked_email: string|null,
     *     masked_phone: string|null,
     * }
     */
    protected function consignmentContext(Request $request, array $preview, ?Vehicle $vehicle = null): array
    {
        $user = $request->user();

        $empty = [
            'available' => false,
            'required' => false,
            'owner_name' => null,
            'owner_email' => null,
            'owner_phone' => null,
            'contact_locked' => false,
            'masked_email' => null,
            'masked_phone' => null,
        ];

        if ($user === null || ! $user->isGarage()) {
            return $empty;
        }

        $registeredOwner = $vehicle?->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->first();

        if ($registeredOwner !== null && $registeredOwner->isGarage()) {
            $registeredOwner = null;
        }

        return [
            'available' => true,
            'required' => $this->crlvBelongsToSomeoneElse($user, $preview),
            'owner_name' => $registeredOwner?->name ?? ($preview['owner_name'] ?? null),
            'owner_email' => null,
            'owner_phone' => null,
            'contact_locked' => $registeredOwner !== null,
            'masked_email' => ContactMask::email($registeredOwner?->email),
            'masked_phone' => ContactMask::phone($registeredOwner?->phone),
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function crlvBelongsToSomeoneElse(User $user, array $preview): bool
    {
        $userDocument = $user->normalizedDocument();
        $ownerDocument = preg_replace('/\D/', '', (string) ($preview['owner_document'] ?? '')) ?: null;

        if ($userDocument === null || $ownerDocument === null) {
            return true;
        }

        return $userDocument !== $ownerDocument;
    }

    /**
     * True when the vehicle already belongs to a registered user, in which case we notify
     * them through their account instead of asking the garage for a contact.
     */
    protected function consignmentContactIsLocked(?Vehicle $vehicle): bool
    {
        if ($vehicle === null) {
            return false;
        }

        $owner = $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->first();

        return $owner !== null && ! $owner->isGarage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function consignmentValidationRules(bool $consigning, bool $contactLocked = false): array
    {
        if (! $consigning) {
            return ['is_consignment' => ['nullable', 'boolean']];
        }

        $rules = [
            'is_consignment' => ['nullable', 'boolean'],
            'consignment_owner_name' => ['required', 'string', 'max:255'],
            'consignment_declaration' => ['required', 'accepted'],
            'power_of_attorney' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        if ($contactLocked) {
            // The owner already has an account; we notify them through it and never ask the
            // garage for — nor show it — their contact.
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
            'power_of_attorney.mimes' => 'A procuração deve ser um arquivo PDF.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function startConsignment(
        Request $request,
        Vehicle $vehicle,
        ?CrlvParseResult $crlv,
        array $data,
    ): VehicleConsignment {
        $powerOfAttorneyPath = $request->hasFile('power_of_attorney')
            ? $request->file('power_of_attorney')->store('procuracoes', AppStorage::diskName())
            : null;

        return app(VehicleConsignmentService::class)->start(
            $request->user(),
            $vehicle,
            [
                'owner_name' => (string) $data['consignment_owner_name'],
                'owner_email' => $data['consignment_owner_email'] ?? null,
                'owner_phone' => $data['consignment_owner_phone'] ?? null,
                'owner_document' => $crlv?->normalizedOwnerDocument(),
                'declaration_ip' => $request->ip(),
                'declaration_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
            $powerOfAttorneyPath,
        );
    }

    protected function consignmentSuccessMessage(VehicleConsignment $consignment): string
    {
        if ($consignment->isHistoryReviewPending()) {
            return 'Veículo adicionado em consignação. A procuração foi enviada para análise e o histórico anterior será liberado após a aprovação.';
        }

        return 'Veículo adicionado em consignação. Você já pode registrar manutenções; o histórico anterior do veículo continua restrito ao proprietário.';
    }
}
