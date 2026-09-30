<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A busca pública da API (GET /api/v1/vehicles/search/{identifier}) não exige login. Chassi e
 * RENAVAM saem parciais para visitante e para quem não é dono do veículo, como na busca da web
 * (.ai/rules/public-lookup.md); o dono, com o token do app ou a sessão da web, recebe os números
 * inteiros. identifiers_masked diz ao app qual dos dois ele recebeu; as chaves não mudam.
 */
class PublicVehicleSearchApiMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const MASKED_CHASSIS = '9BW••••••••••4251';

    private const MASKED_RENAVAM = '•••••••8901';

    public function test_guest_receives_masked_chassis_and_renavam(): void
    {
        $this->vehicle();

        $response = $this->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.license_plate', 'ABC1D23')
            ->assertJsonPath('data.chassis', self::MASKED_CHASSIS)
            ->assertJsonPath('data.renavam', self::MASKED_RENAVAM)
            ->assertJsonPath('data.identifiers_masked', true);

        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString(self::CHASSIS, $payload);
        $this->assertStringNotContainsString(self::RENAVAM, $payload);
    }

    public function test_search_by_the_full_chassis_still_masks_it_in_the_answer(): void
    {
        $this->vehicle();

        $this->getJson('/api/v1/vehicles/search/'.self::CHASSIS)
            ->assertOk()
            ->assertJsonPath('data.matched_by', 'chassis')
            ->assertJsonPath('data.chassis', self::MASKED_CHASSIS)
            ->assertJsonPath('data.renavam', self::MASKED_RENAVAM)
            ->assertJsonPath('data.identifiers_masked', true);
    }

    public function test_signed_in_account_that_does_not_own_the_vehicle_receives_masked_values(): void
    {
        $vehicle = $this->vehicle();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
        $stranger = User::factory()->asUser()->create();

        $this->withToken(SanctumMobileToken::issue($stranger))
            ->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.chassis', self::MASKED_CHASSIS)
            ->assertJsonPath('data.renavam', self::MASKED_RENAVAM)
            ->assertJsonPath('data.identifiers_masked', true);
    }

    public function test_previous_owner_receives_masked_values(): void
    {
        $vehicle = $this->vehicle();
        $seller = User::factory()->asUser()->create()->refresh();
        $seller->vehicles()->attach($vehicle->id, [
            'purchase_date' => now()->subYears(2),
            'sale_date' => now()->subMonth(),
            'is_current_owner' => false,
            'tenant_id' => $seller->tenant_id,
        ]);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $this->withToken(SanctumMobileToken::issue($seller))
            ->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.chassis', self::MASKED_CHASSIS)
            ->assertJsonPath('data.renavam', self::MASKED_RENAVAM)
            ->assertJsonPath('data.identifiers_masked', true);
    }

    public function test_owner_with_the_app_token_receives_the_full_numbers(): void
    {
        $vehicle = $this->vehicle();
        $owner = User::factory()->asUser()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->withToken(SanctumMobileToken::issue($owner))
            ->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.chassis', self::CHASSIS)
            ->assertJsonPath('data.renavam', self::RENAVAM)
            ->assertJsonPath('data.identifiers_masked', false);
    }

    public function test_owner_signed_in_through_the_api_guard_receives_the_full_numbers(): void
    {
        $vehicle = $this->vehicle();
        $owner = $this->actingAsApiUser();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.chassis', self::CHASSIS)
            ->assertJsonPath('data.renavam', self::RENAVAM)
            ->assertJsonPath('data.identifiers_masked', false);
    }

    /**
     * Chassi anterior a 1990 (9 caracteres): com 3 + 4 à mostra sobravam 2 escondidos, e a própria
     * busca pelo chassi (igualdade exata) achava o resto em pouco mais de mil tentativas.
     */
    public function test_short_legacy_chassis_hides_most_of_it(): void
    {
        Vehicle::factory()->create([
            'license_plate' => 'BAA1985',
            'year' => 1985,
            'chassis' => 'BA1234567',
            'renavam' => '98765432109',
        ]);

        $response = $this->getJson('/api/v1/vehicles/search/98765432109')
            ->assertOk()
            ->assertJsonPath('data.chassis', '••••••567')
            ->assertJsonPath('data.identifiers_masked', true);

        $this->assertStringNotContainsString('BA1', (string) $response->json('data.chassis'));
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
        ]);
    }
}
