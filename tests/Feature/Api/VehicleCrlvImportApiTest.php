<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\V1\VehicleCrlvMessages;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Crlv\CrlvParseException;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cadastro pelo CRLV-e no app (App\Http\Controllers\Api\VehicleCrlvController): a leitura decide
 * entre veículo novo, vínculo e "já é seu", e só o token da leitura feita pelo servidor verifica a
 * posse na gravação.
 */
class VehicleCrlvImportApiTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE = 'ABC1D23';

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const CRV = '246813579024';

    private const OWNER_DOCUMENT = '52998224725';

    public function test_reading_a_new_vehicle_returns_the_fields_and_a_token(): void
    {
        $this->actingAsApiUser($this->account());

        $this->readCrlv($this->crlv())
            ->assertOk()
            ->assertJsonPath('data.mode', 'new')
            ->assertJsonPath('data.ownership', 'owner')
            ->assertJsonPath('data.vehicle.license_plate', self::PLATE)
            ->assertJsonPath('data.vehicle.chassis', self::CHASSIS)
            ->assertJsonPath('data.vehicle.crv_number', self::CRV)
            ->assertJsonPath('data.existing_vehicle', null)
            ->assertJsonPath('data.crlv_verification_token', $this->crlv()->verificationToken())
            ->assertJsonMissingPath('data.vehicle.owner_document')
            ->assertJsonMissingPath('data.vehicle.owner_name');
    }

    public function test_store_with_the_token_registers_the_vehicle_with_verified_ownership(): void
    {
        $user = $this->actingAsApiUser($this->account());
        $token = $this->readCrlv($this->crlv())->json('data.crlv_verification_token');

        $this->postJson('/api/v1/vehicles', $this->formData(['crlv_verification_token' => $token]))
            ->assertCreated()
            ->assertJsonPath('data.license_plate', self::PLATE);

        $vehicle = Vehicle::findByChassis(self::CHASSIS);
        $this->assertSame(self::CRV, $vehicle->crv_number);
        $pivot = $user->vehicles()->where('vehicle_id', $vehicle->id)->first()->pivot;
        $this->assertNotNull($pivot->ownership_verified_at);
        $this->assertSame((int) now()->year, (int) $pivot->crlv_exercise_year);
        $this->assertDatabaseMissing('user_vehicles', ['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'terms_accepted_at' => null]);
        $this->assertDatabaseHas('vehicle_plates', ['vehicle_id' => $vehicle->id, 'source' => 'crlv_import']);
    }

    public function test_store_with_the_token_is_used_once(): void
    {
        $this->actingAsApiUser($this->account());
        $token = $this->readCrlv($this->crlv())->json('data.crlv_verification_token');
        $this->postJson('/api/v1/vehicles', $this->formData(['crlv_verification_token' => $token]))->assertCreated();

        Vehicle::query()->delete();

        $this->postJson('/api/v1/vehicles', $this->formData(['crlv_verification_token' => $token]))
            ->assertUnprocessable()
            ->assertJsonPath('message', VehicleCrlvMessages::EXPIRED);
    }

    public function test_store_refuses_a_plate_that_is_not_the_one_on_the_crlv(): void
    {
        $this->actingAsApiUser($this->account());
        $token = $this->readCrlv($this->crlv())->json('data.crlv_verification_token');

        $this->postJson('/api/v1/vehicles', $this->formData(['license_plate' => 'XYZ9A87', 'crlv_verification_token' => $token]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A placa informada não confere com o CRLV-e.');

        $this->assertNull(Vehicle::findByChassis(self::CHASSIS));
    }

    public function test_token_read_by_another_account_does_not_verify_ownership(): void
    {
        $this->actingAsApiUser($this->account());
        $token = $this->readCrlv($this->crlv())->json('data.crlv_verification_token');

        $this->actingAsApiUser($this->account());

        $this->postJson('/api/v1/vehicles', $this->formData(['crlv_verification_token' => $token]))
            ->assertUnprocessable()
            ->assertJsonPath('message', VehicleCrlvMessages::EXPIRED);
    }

    public function test_unreadable_pdf_answers_422_with_the_reader_message(): void
    {
        $this->actingAsApiUser($this->account());
        $this->mock(CrlvPdfParser::class, function ($parser): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andThrow(new CrlvParseException('Não foi possível ler a placa do CRLV-e.'));
        });

        $this->postJson('/api/v1/vehicles/crlv', ['crlv' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Não foi possível ler a placa do CRLV-e.')
            ->assertJsonPath('errors.crlv.0', 'Não foi possível ler a placa do CRLV-e.');
    }

    public function test_expired_exercise_is_refused(): void
    {
        $this->actingAsApiUser($this->account());

        $this->readCrlv($this->crlv(exerciseYear: (int) now()->year - 3))
            ->assertUnprocessable()
            ->assertJsonPath('errors.crlv.0', fn (string $message) => str_starts_with($message, 'O CRLV-e precisa ser do exercício'));
    }

    public function test_missing_file_is_refused(): void
    {
        $this->actingAsApiUser($this->account());

        $this->postJson('/api/v1/vehicles/crlv', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['crlv' => 'Escolha o PDF do CRLV-e para continuar.']);
    }

    public function test_reading_a_registered_vehicle_offers_the_claim_without_its_identifiers(): void
    {
        $this->ownedVehicle();
        $this->actingAsApiUser($this->account(self::OWNER_DOCUMENT));

        $this->readCrlv($this->crlv())
            ->assertOk()
            ->assertJsonPath('data.mode', 'claim')
            ->assertJsonPath('data.ownership', 'owner')
            ->assertJsonPath('data.existing_vehicle.brand', 'Volkswagen')
            ->assertJsonMissingPath('data.existing_vehicle.chassis')
            ->assertJsonMissingPath('data.existing_vehicle.renavam');
    }

    public function test_claim_with_the_token_takes_the_vehicle_from_the_previous_owner(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $claimant = $this->actingAsApiUser($this->account(self::OWNER_DOCUMENT));
        $token = $this->readCrlv($this->crlv())->json('data.crlv_verification_token');

        $this->postJson('/api/v1/vehicles/crlv/claim', ['crlv_verification_token' => $token])
            ->assertOk()
            ->assertJsonPath('data.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.pending_workshop_records_count', 0);

        $this->assertTrue($this->isCurrentOwner($claimant, $vehicle));
        $this->assertFalse($this->isCurrentOwner($owner, $vehicle));
    }

    public function test_claim_from_another_owner_with_a_crlv_in_someone_elses_document_points_to_the_site(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $claimant = $this->actingAsApiUser($this->account('11144477735'));
        $read = $this->readCrlv($this->crlv())->assertJsonPath('data.ownership', 'consignment');

        $this->postJson('/api/v1/vehicles/crlv/claim', ['crlv_verification_token' => $read->json('data.crlv_verification_token')])
            ->assertUnprocessable()
            ->assertJsonPath('message', VehicleCrlvMessages::POWER_OF_ATTORNEY_ON_WEB);

        $this->assertTrue($this->isCurrentOwner($owner, $vehicle));
        $this->assertFalse($this->isCurrentOwner($claimant, $vehicle));
    }

    public function test_claim_with_a_crlv_that_does_not_prove_the_vehicle_is_refused(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $this->actingAsApiUser($this->account(self::OWNER_DOCUMENT));
        $token = $this->readCrlv($this->crlv(chassis: null))->json('data.crlv_verification_token');

        $this->postJson('/api/v1/vehicles/crlv/claim', ['crlv_verification_token' => $token])
            ->assertUnprocessable();

        $this->assertTrue($this->isCurrentOwner($owner, $vehicle));
    }

    public function test_claim_without_a_reading_is_refused(): void
    {
        $this->actingAsApiUser($this->account());

        $this->postJson('/api/v1/vehicles/crlv/claim', ['crlv_verification_token' => str_repeat('a', 64)])
            ->assertUnprocessable()
            ->assertJsonPath('message', VehicleCrlvMessages::EXPIRED);
    }

    public function test_reading_the_crlv_of_a_vehicle_the_account_already_owns_says_so(): void
    {
        [$owner] = $this->ownedVehicle();
        $this->actingAsApiUser($owner);

        $this->readCrlv($this->crlv())
            ->assertOk()
            ->assertJsonPath('data.mode', 'linked');
    }

    public function test_manual_store_without_the_token_still_works_unverified(): void
    {
        $user = $this->actingAsApiUser($this->account());

        $this->postJson('/api/v1/vehicles', $this->formData())->assertCreated();

        $vehicle = Vehicle::findByChassis(self::CHASSIS);
        $this->assertNull($user->vehicles()->where('vehicle_id', $vehicle->id)->first()->pivot->ownership_verified_at);
    }

    private function readCrlv(CrlvParseResult $crlv): TestResponse
    {
        $this->mock(CrlvPdfParser::class, function ($parser) use ($crlv): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andReturn($crlv);
        });

        return $this->postJson('/api/v1/vehicles/crlv', ['crlv' => $this->pdf()]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('CRLV-e.pdf', 100, 'application/pdf');
    }

    private function crlv(?string $chassis = self::CHASSIS, ?int $exerciseYear = null): CrlvParseResult
    {
        return new CrlvParseResult(
            licensePlate: self::PLATE,
            renavam: self::RENAVAM,
            brand: 'Volkswagen',
            model: 'Gol',
            year: 2020,
            color: 'Prata',
            chassis: $chassis,
            brandMatched: true,
            modelMatched: true,
            crvNumber: self::CRV,
            exerciseYear: $exerciseYear ?? (int) now()->year,
            ownerName: 'Titular do CRLV-e',
            ownerDocument: self::OWNER_DOCUMENT,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return $overrides + [
            'license_plate' => self::PLATE,
            'renavam' => self::RENAVAM,
            'brand' => 'Volkswagen',
            'model' => 'Gol',
            'year' => 2020,
            'color' => 'Prata',
            'chassis' => self::CHASSIS,
            'current_kilometers' => 42000,
            'terms_accepted' => true,
        ];
    }

    private function account(?string $document = null): User
    {
        return User::factory()->asUser()->create(['document' => $document])->refresh();
    }

    /**
     * @return array{User, Vehicle}
     */
    private function ownedVehicle(): array
    {
        $owner = $this->account();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => self::PLATE,
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
            'crv_number' => self::CRV,
            'brand' => 'Volkswagen',
            'model' => 'Gol',
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner, $vehicle];
    }

    private function isCurrentOwner(User $account, Vehicle $vehicle): bool
    {
        return $account->vehicles()
            ->where('vehicle_id', $vehicle->id)
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->exists();
    }
}
