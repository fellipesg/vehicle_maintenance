<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use App\Services\Crlv\CrlvExerciseValidator;
use App\Services\Crlv\CrlvParseResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleOwnershipService
{
    public function __construct(
        private readonly CrlvExerciseValidator $exerciseValidator,
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

        if ($renavam === '' || $crvNumber === '') {
            throw new RuntimeException('RENAVAM e número do CRV são obrigatórios para o primeiro cadastro.');
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

        return DB::transaction(function () use ($user, $vehicleData, $crlv, $renavam, $crvNumber, $ownershipType) {
            $vehicle = Vehicle::create([
                'license_plate' => strtoupper($vehicleData['license_plate']),
                'renavam' => $renavam,
                'crv_number' => $crvNumber,
                'brand' => $vehicleData['brand'],
                'model' => $vehicleData['model'],
                'year' => $vehicleData['year'],
                'color' => $vehicleData['color'] ?? null,
                'chassis' => isset($vehicleData['chassis']) ? strtoupper($vehicleData['chassis']) : null,
                'motorization' => $vehicleData['motorization'] ?? null,
                'engine' => $vehicleData['engine'] ?? null,
            ]);

            $this->attachUserToVehicle($user, $vehicle, $crlv, $ownershipType);

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

    public function claimExisting(User $user, Vehicle $vehicle, CrlvParseResult $crlv): Vehicle
    {
        $this->exerciseValidator->assertAcceptable($crlv->exerciseYear);
        $this->assertCrlvMatchesVehicle($crlv, $vehicle);

        if ($user->vehicles()->where('vehicle_id', $vehicle->id)->exists()) {
            throw new RuntimeException('Este veículo já está vinculado à sua conta.');
        }

        $ownershipType = $this->resolveOwnershipType($user, $crlv);

        if ($ownershipType === 'consignment') {
            throw new RuntimeException('consignment_required');
        }

        return DB::transaction(function () use ($user, $vehicle, $crlv, $ownershipType) {
            DB::table('user_vehicles')
                ->where('vehicle_id', $vehicle->id)
                ->update(['is_current_owner' => false]);

            $vehicle->update([
                'license_plate' => strtoupper($crlv->licensePlate),
                'crv_number' => $crlv->normalizedCrvNumber() ?? $vehicle->crv_number,
            ]);

            $this->attachUserToVehicle($user, $vehicle, $crlv, 'owner');

            return $vehicle->fresh();
        });
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

        $userDocument = $user->normalizedDocument();
        $ownerDocument = $crlv->normalizedOwnerDocument();

        if ($userDocument === null || $ownerDocument === null) {
            return 'consignment';
        }

        return $userDocument === $ownerDocument ? 'owner' : 'consignment';
    }

    /**
     * @param  array<string, mixed>  $vehicleData
     */
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

    private function assertCrlvMatchesVehicle(CrlvParseResult $crlv, Vehicle $vehicle): void
    {
        if ($crlv->normalizedRenavam() !== $this->normalizeDigits($vehicle->renavam)) {
            throw new RuntimeException('O RENAVAM do CRLV-e não confere com o veículo cadastrado.');
        }

        if (strtoupper($crlv->licensePlate) !== strtoupper($vehicle->license_plate)) {
            throw new RuntimeException('A placa do CRLV-e não confere com o veículo cadastrado.');
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
    ): void {
        $user->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => $ownershipType === 'owner',
            'tenant_id' => $user->tenant_id,
            'ownership_verified_at' => $crlv ? now() : null,
            'crlv_exercise_year' => $crlv?->exerciseYear,
            'owner_document' => $crlv?->normalizedOwnerDocument(),
            'ownership_type' => $ownershipType,
        ]);
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
