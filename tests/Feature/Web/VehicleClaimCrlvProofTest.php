<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleClaimedByAnotherAccountNotification;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Vínculo a um veículo que já está na RevisaLog com o CRLV-e (VehicleOwnershipService::claimExisting).
 * O leitor só lê o texto do PDF, sem conferir a assinatura, então:
 *
 * - o CRLV-e precisa trazer o chassi inteiro, igual ao cadastrado (a busca só mostra parte dele);
 *   veículo sem chassi cadastrado só com o CRV conferido, e o RENAVAM sozinho não basta;
 * - tirar o veículo de outro dono atual pede o CRLV-e no CPF/CNPJ da conta (também no
 *   Proprietário); uma cópia do documento do dono segue para a procuração e o dono continua dono;
 * - quem perde o veículo recebe um aviso por e-mail.
 */
class VehicleClaimCrlvProofTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE = 'ABC1D23';

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const CRV = '246813579024';

    private const CRLV_OWNER_DOCUMENT = '52998224725';

    public function test_crlv_without_the_chassis_does_not_claim_the_vehicle(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle(['crv_number' => null]);
        $claimant = $this->account(self::CRLV_OWNER_DOCUMENT);

        $this->claim($claimant, $this->crlv(chassis: null))
            ->assertRedirect(route('user.vehicles.claim.preview'))
            ->assertSessionHasErrors(['vehicle' => 'O CRLV-e enviado não traz o chassi, e sem ele não dá para confirmar o veículo. Envie o PDF do CRLV-e digital exportado pela Carteira Digital de Trânsito.']);

        $this->assertStillOwnedBy($owner, $vehicle, $claimant);
        $this->actingAs($claimant)->get(route('user.vehicles.show', $vehicle))->assertForbidden();
    }

    public function test_crlv_with_another_chassis_does_not_claim_the_vehicle(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $claimant = $this->account(self::CRLV_OWNER_DOCUMENT);

        $this->claim($claimant, $this->crlv(chassis: '9BWZZZ377VT009999'))
            ->assertSessionHasErrors(['vehicle' => 'O chassi do CRLV-e não confere com o veículo cadastrado.']);

        $this->assertStillOwnedBy($owner, $vehicle, $claimant);
    }

    public function test_renavam_alone_does_not_claim_a_vehicle_without_chassis_and_crv(): void
    {
        $vehicle = $this->vehicle(['chassis' => null, 'crv_number' => null]);
        $claimant = $this->account(self::CRLV_OWNER_DOCUMENT);

        $this->claim($claimant, $this->crlv())
            ->assertSessionHasErrors(['vehicle' => 'Este veículo não tem chassi nem número do CRV cadastrados para conferir com o CRLV-e. Fale com a equipe RevisaLog para vinculá-lo à sua conta.']);

        $this->assertDatabaseMissing('user_vehicles', ['user_id' => $claimant->id, 'vehicle_id' => $vehicle->id]);
        $this->assertNull($vehicle->fresh()->chassis);
    }

    public function test_vehicle_without_chassis_is_claimed_when_the_crv_matches(): void
    {
        $vehicle = $this->vehicle(['chassis' => null, 'crv_number' => self::CRV]);
        $claimant = $this->account(null);

        $this->claim($claimant, $this->crlv())
            ->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->isCurrentOwner($claimant, $vehicle));
        $this->assertSame(self::CHASSIS, $vehicle->fresh()->chassis);
    }

    public function test_copy_of_the_owners_crlv_goes_to_the_power_of_attorney_and_the_owner_keeps_the_vehicle(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownedVehicle();
        $claimant = $this->account('11144477735');

        $this->importCrlv($claimant, $this->crlv());
        $this->actingAs($claimant)->get(route('user.vehicles.claim.preview'))
            ->assertOk()
            ->assertSee('Continuar para a procuração')
            ->assertDontSee('Vincular à minha conta');

        $this->postClaim($claimant)->assertRedirect(route('user.vehicles.consignment'));

        $this->assertStillOwnedBy($owner, $vehicle, $claimant);
        Notification::assertNothingSent();
    }

    public function test_account_without_document_cannot_take_the_vehicle_from_its_owner(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $claimant = $this->account(null);

        $this->claim($claimant, $this->crlv())->assertRedirect(route('user.vehicles.consignment'));

        $this->assertStillOwnedBy($owner, $vehicle, $claimant);
    }

    public function test_buyer_with_the_crlv_in_their_document_takes_the_vehicle_and_the_seller_is_told(): void
    {
        Notification::fake();
        [$seller, $vehicle] = $this->ownedVehicle();
        $buyer = $this->account(self::CRLV_OWNER_DOCUMENT);

        $this->claim($buyer, $this->crlv())
            ->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHas('success', 'Veículo vinculado à sua conta.');

        $this->assertTrue($this->isCurrentOwner($buyer, $vehicle));
        $this->assertFalse($this->isCurrentOwner($seller, $vehicle));
        $this->actingAs($buyer)->get(route('user.vehicles.show', $vehicle))->assertOk()->assertSee(self::CHASSIS);

        Notification::assertSentTo($seller, VehicleClaimedByAnotherAccountNotification::class);
        Notification::assertNotSentTo($buyer, VehicleClaimedByAnotherAccountNotification::class);
    }

    public function test_vehicle_without_a_current_owner_is_claimed_by_an_owner_account_without_document(): void
    {
        Notification::fake();
        $vehicle = $this->vehicle();
        $claimant = $this->account(null);

        $this->claim($claimant, $this->crlv())->assertRedirect(route('user.vehicles.covers', $vehicle));

        $this->assertTrue($this->isCurrentOwner($claimant, $vehicle));
        Notification::assertNothingSent();
    }

    public function test_former_owner_buying_the_vehicle_back_reuses_the_old_link(): void
    {
        Notification::fake();
        $formerOwner = $this->account(self::CRLV_OWNER_DOCUMENT);
        [$currentOwner, $vehicle] = $this->ownedVehicle();
        $formerOwner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYears(3),
            'sale_date' => now()->subYear(),
            'tenant_id' => $formerOwner->tenant_id,
        ]);

        $this->claim($formerOwner, $this->crlv())
            ->assertRedirect(route('user.vehicles.covers', $vehicle))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $formerOwner->vehicles()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $formerOwner->id,
            'vehicle_id' => $vehicle->id,
            'is_current_owner' => true,
            'sale_date' => null,
            'ownership_type' => 'owner',
        ]);
        $this->assertFalse($this->isCurrentOwner($currentOwner, $vehicle));
        Notification::assertSentTo($currentOwner, VehicleClaimedByAnotherAccountNotification::class);
    }

    public function test_current_owner_cannot_claim_the_vehicle_again(): void
    {
        [$owner, $vehicle] = $this->ownedVehicle();
        $owner->update(['document' => self::CRLV_OWNER_DOCUMENT]);

        $this->claim($owner, $this->crlv())
            ->assertSessionHasErrors(['vehicle' => 'Este veículo já está vinculado à sua conta.']);

        $this->assertTrue($this->isCurrentOwner($owner, $vehicle));
    }

    public function test_notice_to_the_previous_owner_does_not_identify_the_new_account(): void
    {
        $seller = $this->account(null, ['name' => 'Ana Lima']);
        $vehicle = $this->vehicle(['brand' => 'Volkswagen', 'model' => 'Gol']);

        $mail = (new VehicleClaimedByAnotherAccountNotification($vehicle))->toMail($seller);
        $rendered = (string) $mail->render();

        $this->assertSame('Volkswagen Gol foi vinculado a outra conta na RevisaLog', $mail->subject);
        $this->assertSame('Olá, Ana!', $mail->greeting);
        $this->assertSame('RevisaLog', $mail->salutation);
        $this->assertStringContainsString('placa '.self::PLATE, $rendered);
        $this->assertStringContainsString('Se você não vendeu, responda este e-mail', $rendered);
        $this->assertStringNotContainsString(self::CHASSIS, $rendered);
        $this->assertStringNotContainsString(self::RENAVAM, $rendered);
    }

    public function test_dealer_that_bought_the_consigned_vehicle_turns_its_consignment_link_into_ownership(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownedVehicle();
        $dealer = User::factory()->asGarage()->create(['document' => self::CRLV_OWNER_DOCUMENT])->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        $this->claim($dealer, $this->crlv(), 'garage')
            ->assertRedirect(route('garage.vehicles.covers', $vehicle))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $dealer->id,
            'vehicle_id' => $vehicle->id,
            'is_current_owner' => true,
            'ownership_type' => 'owner',
        ]);
        $this->assertFalse($this->isCurrentOwner($owner, $vehicle));
        Notification::assertSentTo($owner, VehicleClaimedByAnotherAccountNotification::class);
    }

    private function claim(User $claimant, CrlvParseResult $crlv, string $portal = 'user'): TestResponse
    {
        $this->importCrlv($claimant, $crlv, $portal);

        return $this->postClaim($claimant, $portal);
    }

    private function importCrlv(User $claimant, CrlvParseResult $crlv, string $portal = 'user'): void
    {
        $this->mock(CrlvPdfParser::class, function ($parser) use ($crlv): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andReturn($crlv);
        });

        $this->actingAs($claimant)
            ->post(route("{$portal}.vehicles.import-crlv"), ['crlv' => UploadedFile::fake()->create('CRLV-e.pdf', 100, 'application/pdf')])
            ->assertRedirect(route("{$portal}.vehicles.claim.preview"));
    }

    private function postClaim(User $claimant, string $portal = 'user'): TestResponse
    {
        return $this->actingAs($claimant)
            ->from(route("{$portal}.vehicles.claim.preview"))
            ->post(route("{$portal}.vehicles.claim.store"), ['crlv_verification_token' => session('crlv_verification.token')]);
    }

    private function crlv(?string $chassis = self::CHASSIS, ?string $crv = self::CRV): CrlvParseResult
    {
        return new CrlvParseResult(
            licensePlate: self::PLATE,
            renavam: self::RENAVAM,
            brand: 'Volkswagen',
            model: 'Gol',
            year: 2020,
            chassis: $chassis,
            crvNumber: $crv,
            exerciseYear: (int) now()->year,
            ownerName: 'Titular do CRLV-e',
            ownerDocument: self::CRLV_OWNER_DOCUMENT,
        );
    }

    private function assertStillOwnedBy(User $owner, Vehicle $vehicle, User $claimant): void
    {
        $this->assertTrue($this->isCurrentOwner($owner, $vehicle));
        $this->assertFalse($this->isCurrentOwner($claimant, $vehicle));
    }

    private function isCurrentOwner(User $account, Vehicle $vehicle): bool
    {
        return $account->vehicles()
            ->where('vehicle_id', $vehicle->id)
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function account(?string $document, array $attributes = []): User
    {
        return User::factory()->asUser()->create(['document' => $document] + $attributes)->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{User, Vehicle}
     */
    private function ownedVehicle(array $attributes = []): array
    {
        $owner = $this->account(null);
        $vehicle = $this->vehicle($attributes);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner, $vehicle];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::factory()->create($attributes + [
            'license_plate' => self::PLATE,
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
            'crv_number' => self::CRV,
            'brand' => 'Volkswagen',
            'model' => 'Gol',
        ]);
    }
}
