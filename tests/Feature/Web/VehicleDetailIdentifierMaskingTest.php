<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ficha do veículo no Lojista (garage.vehicles.show) e no Proprietário (user.vehicles.show): chassi e
 * RENAVAM inteiros, CRV, motor e "Copiar chassi" só para o dono atual (VehiclePolicy::update), a
 * mesma regra da API (identifiers_masked). Quem abre a ficha por uma consignação aprovada vê os
 * números parciais (App\Support\Vehicle\VehicleIdentifierMask).
 */
class VehicleDetailIdentifierMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const CRV = '246813579024';

    private const ENGINE = 'MTR7Q4X9Z21';

    private const MASKED_CHASSIS = '9BW••••••••••4251';

    private const MASKED_RENAVAM = '•••••••8901';

    public function test_dealer_with_an_approved_consignment_sees_partial_numbers(): void
    {
        $vehicle = $this->ownedVehicle();
        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        $this->approveConsignment($dealer, $vehicle);

        $this->actingAsApiUser($dealer);
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertJsonPath('data.identifiers_masked', true);

        $this->assertMasked($this->actingAs($dealer)->get(route('garage.vehicles.show', $vehicle))->assertOk());
    }

    public function test_dealer_that_owns_the_stock_vehicle_sees_the_full_numbers(): void
    {
        $dealer = User::factory()->asGarage()->create()->refresh();
        $vehicle = $this->vehicle();
        $this->attachVehicleToUser($dealer, $vehicle);

        $this->assertFull($this->actingAs($dealer)->get(route('garage.vehicles.show', $vehicle))->assertOk());
    }

    public function test_owner_account_viewing_through_an_approved_consignment_sees_partial_numbers(): void
    {
        $vehicle = $this->ownedVehicle();
        $account = User::factory()->asUser()->create();
        $this->approveConsignment($account, $vehicle);

        $this->assertMasked($this->actingAs($account)->get(route('user.vehicles.show', $vehicle))->assertOk());
    }

    public function test_current_owner_sees_the_full_numbers(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicle();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->assertFull($this->actingAs($owner)->get(route('user.vehicles.show', $vehicle))->assertOk());
    }

    private function assertMasked(TestResponse $response): void
    {
        $response->assertSee(self::MASKED_CHASSIS)
            ->assertSee(self::MASKED_RENAVAM)
            ->assertDontSee(self::CHASSIS)
            ->assertDontSee(self::RENAVAM)
            ->assertDontSee(self::CRV)
            ->assertDontSee(self::ENGINE)
            ->assertDontSee('Copiar chassi');
    }

    private function assertFull(TestResponse $response): void
    {
        $response->assertSee(self::CHASSIS)
            ->assertSee(self::RENAVAM)
            ->assertSee(self::CRV)
            ->assertSee(self::ENGINE)
            ->assertSee('Copiar chassi')
            ->assertDontSee(self::MASKED_CHASSIS);
    }

    private function ownedVehicle(): Vehicle
    {
        $vehicle = $this->vehicle();
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        return $vehicle;
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
            'crv_number' => self::CRV,
            'engine' => self::ENGINE,
        ]);
    }

    private function approveConsignment(User $account, Vehicle $vehicle): void
    {
        VehicleAccessGrant::create([
            'user_id' => $account->id,
            'vehicle_id' => $vehicle->id,
            'grant_type' => 'consignment',
            'status' => 'approved',
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);
    }
}
