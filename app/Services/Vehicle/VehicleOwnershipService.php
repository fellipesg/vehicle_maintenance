<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use App\Notifications\VehicleClaimedByAnotherAccountNotification;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Maintenance\WorkshopRecordsArrivalNotifier;
use App\Support\VehiclePlateSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class VehicleOwnershipService
{
    public function __construct(
        private readonly CrlvExerciseValidator $exerciseValidator,
        private readonly VehiclePlateHistoryService $plateHistory,
        private readonly WorkshopRecordsArrivalNotifier $arrivalNotifier,
    ) {}

    /**
     * @param  array<string, mixed>  $vehicleData
     */
    public function registerNew(
        User $user,
        array $vehicleData,
        ?CrlvParseResult $crlv = null,
        string $ownershipType = 'owner',
    ): Vehicle {
        $renavam = $this->normalizeDigits($vehicleData['renavam'] ?? '');
        $crvNumber = $this->normalizeDigits($vehicleData['crv_number'] ?? '');
        $chassis = isset($vehicleData['chassis'])
            ? Vehicle::normalizeChassis((string) $vehicleData['chassis'])
            : '';

        if ($renavam === '' || $crvNumber === '') {
            throw new RuntimeException('RENAVAM e número do CRV são obrigatórios para o primeiro cadastro.');
        }

        if ($chassis !== '' && Vehicle::findByChassis($chassis)) {
            throw new RuntimeException('Este veículo já está cadastrado. Envie o CRLV-e para vincular à sua conta.');
        }

        if (Vehicle::findByRenavam($renavam)) {
            throw new RuntimeException('Este veículo já está cadastrado. Envie o CRLV-e para vincular à sua conta.');
        }

        if ($crlv !== null) {
            $this->assertCrlvMatchesRegistration($crlv, $renavam, $crvNumber, $vehicleData);
            $this->exerciseValidator->assertAcceptable($crlv->exerciseYear);
            if ($ownershipType === 'owner') {
                $ownershipType = $this->resolveOwnershipType($user, $crlv);
            }
        }

        return DB::transaction(function () use ($user, $vehicleData, $crlv, $renavam, $crvNumber, $ownershipType, $chassis) {
            $vehicle = Vehicle::create([
                'license_plate' => strtoupper($vehicleData['license_plate']),
                'renavam' => $renavam,
                'crv_number' => $crvNumber,
                'brand' => $vehicleData['brand'],
                'model' => $vehicleData['model'],
                'year' => $vehicleData['year'],
                'color' => $vehicleData['color'] ?? null,
                'chassis' => $chassis !== '' ? $chassis : null,
                'motorization' => $vehicleData['motorization'] ?? null,
                'engine' => $vehicleData['engine'] ?? null,
                'current_kilometers' => (int) $vehicleData['current_kilometers'],
                'odometer_at_registration' => (int) $vehicleData['current_kilometers'],
            ]);

            $this->plateHistory->recordInitialPlate(
                $vehicle,
                $crlv !== null ? 'crlv_import' : 'manual',
                $user,
            );

            $this->attachUserToVehicle($user, $vehicle, $crlv, $ownershipType, acceptedTerms: true);

            return $vehicle;
        });
    }

    public function attachConsignmentUser(User $user, Vehicle $vehicle, CrlvParseResult $crlv): void
    {
        if ($user->vehicles()->where('vehicle_id', $vehicle->id)->exists()) {
            return;
        }

        $this->attachUserToVehicle($user, $vehicle, $crlv, 'consignment');
    }

    /**
     * Posse de um veículo que já está na RevisaLog, com o CRLV-e. O documento precisa provar o
     * veículo (assertCrlvProvesVehicle) e, quando o veículo tem outro dono atual, estar no CPF/CNPJ
     * da conta (resolveClaimOwnershipType); senão o caminho é a procuração ('consignment_required').
     * Quem já teve o veículo (vendeu e comprou de volta, ou o tinha em consignação) reaproveita o
     * vínculo antigo. Os donos anteriores deixam de ser donos atuais e recebem um aviso por e-mail.
     */
    public function claimExisting(User $user, Vehicle $vehicle, CrlvParseResult $crlv): Vehicle
    {
        $this->exerciseValidator->assertAcceptable($crlv->exerciseYear);
        $this->assertCrlvProvesVehicle($crlv, $vehicle);

        $existingLink = $user->vehicles()->where('vehicle_id', $vehicle->id)->first()?->pivot;

        if ($existingLink !== null
            && $existingLink->is_current_owner
            && (int) $existingLink->tenant_id === (int) $user->tenant_id) {
            throw new RuntimeException('Este veículo já está vinculado à sua conta.');
        }

        if ($this->resolveClaimOwnershipType($user, $vehicle, $crlv) === 'consignment') {
            throw new RuntimeException('consignment_required');
        }

        $previousOwners = $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->where('users.id', '!=', $user->id)
            ->get();

        $vehicle = DB::transaction(function () use ($user, $vehicle, $crlv, $existingLink) {
            DB::table('user_vehicles')
                ->where('vehicle_id', $vehicle->id)
                ->update(['is_current_owner' => false]);

            $crlvPlate = VehiclePlateSearch::normalize($crlv->licensePlate);
            $currentPlate = VehiclePlateSearch::normalize((string) $vehicle->license_plate);

            if ($crlvPlate !== '' && $crlvPlate !== $currentPlate) {
                $plateTaken = VehiclePlateSearch::findByPlate($crlvPlate)?->vehicle;

                if ($plateTaken !== null && $plateTaken->id !== $vehicle->id) {
                    throw new RuntimeException('A placa do CRLV-e já está em outro veículo cadastrado. Fale com a equipe RevisaLog para vinculá-lo à sua conta.');
                }

                $this->plateHistory->changePlate($vehicle, $crlvPlate, 'crlv_import', $user);
            }

            $vehicle->update([
                'crv_number' => $crlv->normalizedCrvNumber() ?? $vehicle->crv_number,
                'chassis' => $crlv->chassis !== null && $crlv->chassis !== ''
                    ? Vehicle::normalizeChassis($crlv->chassis)
                    : $vehicle->chassis,
                'renavam' => $this->renavamAfterClaim($vehicle, $crlv),
            ]);

            if ($existingLink !== null) {
                $user->vehicles()->updateExistingPivot(
                    $vehicle->id,
                    $this->ownershipPivotAttributes($user, $crlv, 'owner') + ['sale_date' => null],
                );
            } else {
                $this->attachUserToVehicle($user, $vehicle, $crlv, 'owner');
            }

            return $vehicle->fresh();
        });

        if ($previousOwners->isNotEmpty()) {
            Notification::send($previousOwners, new VehicleClaimedByAnotherAccountNotification($vehicle));
        }

        $this->arrivalNotifier->notify($user, $vehicle);

        return $vehicle;
    }

    /**
     * App: o proprietário cadastra à mão um veículo cujo chassi bate com um veículo que a oficina criou
     * só pelo chassi (sem placa, RENAVAM nem dono). O vínculo NÃO é verificado
     * (ownership_verified_at nulo): sem o CRLV-e ele pode vincular ou recusar o registro mínimo e
     * ocultá-lo, mas não aceitar notas e fotos. Placa e RENAVAM entram dos dados informados quando
     * ninguém mais os usa.
     *
     * @param  array<string, mixed>  $vehicleData
     */
    public function claimUnclaimedWorkshopVehicle(User $user, Vehicle $vehicle, array $vehicleData): Vehicle
    {
        $vehicle = DB::transaction(function () use ($user, $vehicle, $vehicleData): Vehicle {
            $updates = [];
            $plate = VehiclePlateSearch::normalize((string) ($vehicleData['license_plate'] ?? ''));
            $renavam = $this->normalizeDigits((string) ($vehicleData['renavam'] ?? ''));

            if ($renavam !== '' && blank($vehicle->renavam) && Vehicle::findByRenavam($renavam) === null) {
                $updates['renavam'] = $renavam;
            }

            foreach (['color', 'motorization', 'engine'] as $field) {
                if (filled($vehicleData[$field] ?? null) && blank($vehicle->{$field})) {
                    $updates[$field] = $vehicleData[$field];
                }
            }

            if (isset($vehicleData['current_kilometers'])) {
                $updates['current_kilometers'] = max((int) $vehicleData['current_kilometers'], (int) $vehicle->current_kilometers);
            }

            if ($updates !== []) {
                $vehicle->update($updates);
            }

            if ($plate !== '' && blank($vehicle->license_plate) && VehiclePlateSearch::findByPlate($plate) === null) {
                $this->plateHistory->changePlate($vehicle, $plate, 'api', $user);
            }

            $user->vehicles()->attach($vehicle->id, [
                'purchase_date' => $vehicleData['purchase_date'] ?? now(),
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
                'ownership_verified_at' => null,
                'ownership_type' => 'owner',
                'terms_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
            ]);

            return $vehicle->fresh();
        });

        $this->arrivalNotifier->notify($user, $vehicle);

        return $vehicle;
    }

    /**
     * RENAVAM do veículo criado pela oficina vem do CRLV-e no vínculo. Se outro veículo já usa esse
     * número, o vínculo para: não dá para repetir um RENAVAM e a equipe precisa conferir.
     */
    private function renavamAfterClaim(Vehicle $vehicle, CrlvParseResult $crlv): ?string
    {
        if ($this->normalizeDigits((string) $vehicle->renavam) !== '') {
            return $vehicle->renavam;
        }

        $renavam = $crlv->normalizedRenavam();

        if ($renavam === '') {
            return null;
        }

        $other = Vehicle::findByRenavam($renavam);

        if ($other !== null && $other->id !== $vehicle->id) {
            throw new RuntimeException('O RENAVAM do CRLV-e já está em outro veículo cadastrado. Fale com a equipe RevisaLog para vinculá-lo à sua conta.');
        }

        return $renavam;
    }

    public function requestConsignmentAccess(
        User $user,
        Vehicle $vehicle,
        CrlvParseResult $crlv,
        string $powerOfAttorneyPath,
    ): VehicleAccessGrant {
        $this->exerciseValidator->assertAcceptable($crlv->exerciseYear);
        $this->assertCrlvMatchesVehicle($crlv, $vehicle);

        return VehicleAccessGrant::updateOrCreate(
            [
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'grant_type' => 'consignment',
            ],
            [
                'status' => 'pending',
                'power_of_attorney_path' => $powerOfAttorneyPath,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]
        );
    }

    public function resolveOwnershipType(User $user, ?CrlvParseResult $crlv): string
    {
        if (! $user->isGarage() || $crlv === null) {
            return 'owner';
        }

        return self::crlvIsInAccountDocument($user, $crlv->normalizedOwnerDocument()) ? 'owner' : 'consignment';
    }

    /**
     * Vínculo a um veículo que já está na RevisaLog. O lojista segue resolveOwnershipType. Qualquer
     * conta que tiraria o veículo de outro dono atual também precisa do CRLV-e no CPF/CNPJ da própria
     * conta: quem tem só uma cópia do documento do dono (ou um CRLV-e ainda não transferido) segue
     * para a procuração, e o dono atual continua dono. Sem outro dono atual, o Proprietário fica dono.
     */
    public function resolveClaimOwnershipType(User $user, Vehicle $vehicle, CrlvParseResult $crlv): string
    {
        if (! $user->isGarage() && ! self::hasCurrentOwnerOtherThan($user, $vehicle)) {
            return 'owner';
        }

        return self::crlvIsInAccountDocument($user, $crlv->normalizedOwnerDocument()) ? 'owner' : 'consignment';
    }

    /**
     * O veículo é de outra conta agora (vínculo com is_current_owner de outro usuário).
     */
    public static function hasCurrentOwnerOtherThan(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->where('users.id', '!=', $user->id)
            ->exists();
    }

    /**
     * O CPF/CNPJ do proprietário no CRLV-e (só dígitos) é o da conta. Conta sem documento não confere.
     */
    public static function crlvIsInAccountDocument(User $user, ?string $crlvOwnerDocument): bool
    {
        $userDocument = $user->normalizedDocument();
        $ownerDocument = $crlvOwnerDocument !== null ? (preg_replace('/\D/', '', $crlvOwnerDocument) ?: null) : null;

        return $userDocument !== null && $ownerDocument !== null && $userDocument === $ownerDocument;
    }

    public static function findExistingVehicle(CrlvParseResult $crlv): ?Vehicle
    {
        if ($crlv->chassis !== null && $crlv->chassis !== '') {
            $byChassis = Vehicle::findByChassis($crlv->chassis);
            if ($byChassis !== null) {
                return $byChassis;
            }
        }

        $byRenavam = Vehicle::findByRenavam($crlv->renavam);
        if ($byRenavam !== null) {
            return $byRenavam;
        }

        $byPlate = VehiclePlateSearch::findByPlate($crlv->licensePlate);

        return $byPlate?->vehicle;
    }

    /**
     * The API claim has no CRLV file, so the plate and RENAVAM printed on the document
     * stand in as proof. It is weaker than a parsed CRLV, so the caller must not mark
     * the ownership as verified.
     */
    public function documentMatchesVehicle(Vehicle $vehicle, string $licensePlate, string $renavam): bool
    {
        $vehicleRenavam = $this->normalizeDigits((string) $vehicle->renavam);
        $givenRenavam = $this->normalizeDigits($renavam);

        if ($vehicleRenavam === '' || $givenRenavam === '') {
            return false;
        }

        $vehiclePlate = VehiclePlateSearch::normalize((string) $vehicle->license_plate);
        $givenPlate = VehiclePlateSearch::normalize($licensePlate);

        if ($vehiclePlate === '' || $givenPlate === '') {
            return false;
        }

        return $givenRenavam === $vehicleRenavam && $givenPlate === $vehiclePlate;
    }

    private function assertCrlvMatchesRegistration(
        CrlvParseResult $crlv,
        string $renavam,
        string $crvNumber,
        array $vehicleData,
    ): void {
        if ($crlv->normalizedRenavam() !== $renavam) {
            throw new RuntimeException('O RENAVAM informado não confere com o CRLV-e.');
        }

        if ($crlv->normalizedCrvNumber() !== $crvNumber) {
            throw new RuntimeException('O número do CRV informado não confere com o CRLV-e.');
        }

        if (strtoupper($crlv->licensePlate) !== strtoupper((string) ($vehicleData['license_plate'] ?? ''))) {
            throw new RuntimeException('A placa informada não confere com o CRLV-e.');
        }
    }

    /**
     * Conferência para dar a posse de um veículo que já existe. O leitor só lê o texto do PDF (não
     * confere a assinatura nem o QR Code da SENATRAN), então o documento precisa trazer o que não é
     * público: o chassi inteiro, igual ao cadastrado (a busca mostra só parte dele), o RENAVAM e,
     * quando o veículo tem, o número do CRV. Veículo sem chassi cadastrado precisa do CRV conferido;
     * sem chassi e sem CRV, o RENAVAM sozinho não basta.
     */
    private function assertCrlvProvesVehicle(CrlvParseResult $crlv, Vehicle $vehicle): void
    {
        $crlvChassis = $crlv->chassis !== null ? Vehicle::normalizeChassis($crlv->chassis) : '';

        if ($crlvChassis === '') {
            throw new RuntimeException('O CRLV-e enviado não traz o chassi, e sem ele não dá para confirmar o veículo. Envie o PDF do CRLV-e digital exportado pela Carteira Digital de Trânsito.');
        }

        $this->assertCrlvMatchesVehicle($crlv, $vehicle);

        $vehicleChassis = $vehicle->chassis !== null ? Vehicle::normalizeChassis((string) $vehicle->chassis) : '';
        $vehicleCrv = $this->normalizeDigits((string) $vehicle->crv_number);

        if ($vehicleChassis === '' && $vehicleCrv === '') {
            throw new RuntimeException('Este veículo não tem chassi nem número do CRV cadastrados para conferir com o CRLV-e. Fale com a equipe RevisaLog para vinculá-lo à sua conta.');
        }
    }

    private function assertCrlvMatchesVehicle(CrlvParseResult $crlv, Vehicle $vehicle): void
    {
        $crlvChassis = $crlv->chassis !== null ? Vehicle::normalizeChassis($crlv->chassis) : '';
        $vehicleChassis = $vehicle->chassis !== null ? Vehicle::normalizeChassis((string) $vehicle->chassis) : '';

        if ($crlvChassis !== '' && $vehicleChassis !== '' && $crlvChassis !== $vehicleChassis) {
            throw new RuntimeException('O chassi do CRLV-e não confere com o veículo cadastrado.');
        }

        $vehicleRenavam = $this->normalizeDigits((string) $vehicle->renavam);

        if ($vehicleRenavam === '') {
            // Veículo criado pela oficina sem RENAVAM: o chassi inteiro e igual é a prova, e o
            // RENAVAM do documento passa a ser o do cadastro (renavamAfterClaim).
            if ($crlvChassis === '' || $vehicleChassis === '' || $crlvChassis !== $vehicleChassis) {
                throw new RuntimeException('O chassi do CRLV-e não confere com o veículo cadastrado.');
            }
        } elseif ($crlv->normalizedRenavam() !== $vehicleRenavam) {
            throw new RuntimeException('O RENAVAM do CRLV-e não confere com o veículo cadastrado.');
        }

        if ($vehicle->crv_number && $crlv->normalizedCrvNumber() !== $this->normalizeDigits($vehicle->crv_number)) {
            throw new RuntimeException('O número do CRV do CRLV-e não confere com o veículo cadastrado.');
        }
    }

    private function attachUserToVehicle(
        User $user,
        Vehicle $vehicle,
        ?CrlvParseResult $crlv,
        string $ownershipType,
        bool $acceptedTerms = false,
    ): void {
        $user->vehicles()->attach($vehicle->id, $this->ownershipPivotAttributes($user, $crlv, $ownershipType) + [
            'terms_accepted_at' => $acceptedTerms ? now() : null,
            'terms_version' => $acceptedTerms ? config('legal.terms_version') : null,
        ]);
    }

    /**
     * Colunas do vínculo (user_vehicles) de uma posse ou consignação, com ou sem o CRLV-e.
     *
     * @return array<string, mixed>
     */
    private function ownershipPivotAttributes(User $user, ?CrlvParseResult $crlv, string $ownershipType): array
    {
        return [
            'purchase_date' => now(),
            'is_current_owner' => $ownershipType === 'owner',
            'tenant_id' => $user->tenant_id,
            'ownership_verified_at' => $crlv ? now() : null,
            'crlv_exercise_year' => $crlv?->exerciseYear,
            'owner_document' => $crlv?->normalizedOwnerDocument(),
            'ownership_type' => $ownershipType,
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
