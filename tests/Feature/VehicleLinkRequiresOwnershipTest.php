<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use App\Services\User\DeleteUserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/v1/vehicles/{id}/link confirma quem já é o dono atual. Um carro sem dono atual só muda de
 * mãos com a placa e o RENAVAM do documento; sem esses campos a resposta é 422 e o pivot não vira
 * dono. Carro de outra conta continua 403, mesmo com o documento certo.
 */
class VehicleLinkRequiresOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    public function test_current_owner_confirms_the_link_and_keeps_the_full_numbers(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $purchaseDate = $owner->vehicles()->first()->pivot->purchase_date;

        $this->actingAsApiUser($owner);

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link")
            ->assertOk()
            ->assertJsonPath('data.chassis', self::CHASSIS)
            ->assertJsonPath('data.renavam', self::RENAVAM)
            ->assertJsonPath('data.identifiers_masked', false);

        $this->assertEquals($purchaseDate, $owner->vehicles()->first()->pivot->purchase_date);
    }

    public function test_stranger_cannot_link_a_vehicle_its_owner_removed_from_the_account(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $this->declaredBy($owner, $vehicle);

        $this->actingAsApiUser($owner);
        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();
        $this->assertModelExists($vehicle);

        $stranger = $this->actingAsApiUser();
        $vehicleId = $this->getJson('/api/v1/vehicles/search/ABC1D23')
            ->assertOk()
            ->assertJsonPath('data.identifiers_masked', true)
            ->json('data.id');

        $this->assertDocumentRequired($stranger, $vehicleId);
        $this->claimWithDocument($vehicleId)
            ->assertOk()
            ->assertJsonPath('data.identifiers_masked', false)
            ->assertJsonPath('data.chassis', self::CHASSIS);
    }

    public function test_stranger_cannot_link_a_vehicle_left_by_a_deleted_account(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        app(DeleteUserAccount::class)->handle($owner);

        $stranger = $this->actingAsApiUser();

        $this->assertDocumentRequired($stranger, $vehicle->id);
        $this->claimWithDocument($vehicle->id)->assertOk();
        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $stranger->id,
            'vehicle_id' => $vehicle->id,
            'is_current_owner' => true,
        ]);
    }

    public function test_stranger_needs_the_document_to_link_a_vehicle_held_only_on_consignment(): void
    {
        $vehicle = $this->vehicle();
        $this->consignmentDealer($vehicle, 'pending');

        $stranger = $this->actingAsApiUser();

        $this->assertDocumentRequired($stranger, $vehicle->id);
        $this->claimWithDocument($vehicle->id)->assertOk();
    }

    public function test_dealer_with_a_pending_power_of_attorney_cannot_link_and_skip_the_review(): void
    {
        $vehicle = $this->vehicle();
        $dealer = $this->consignmentDealer($vehicle, 'pending');

        $this->actingAsApiUser($dealer);
        $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertForbidden();

        $this->assertDocumentRequired($dealer, $vehicle->id);
        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $dealer->id,
            'vehicle_id' => $vehicle->id,
            'is_current_owner' => false,
            'ownership_type' => 'consignment',
        ]);
    }

    public function test_consignment_dealer_cannot_link_to_edit_or_delete_what_it_declared(): void
    {
        $vehicle = $this->vehicle();
        $dealer = $this->consignmentDealer($vehicle, 'approved');
        $maintenance = $this->declaredBy($dealer, $vehicle);

        $this->actingAsApiUser($dealer);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Alterada'])->assertForbidden();
        $this->assertDocumentRequired($dealer, $vehicle->id);
        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Alterada'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")->assertForbidden();
        $this->assertModelExists($maintenance);
    }

    public function test_seller_cannot_link_again_after_the_buyer_removes_the_vehicle(): void
    {
        [$seller, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($seller, $vehicle);
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $buyer = User::factory()->asUser()->create();
        $this->attachVehicleToUser($buyer, $vehicle);

        $this->actingAsApiUser($buyer);
        $this->deleteJson("/api/v1/vehicles/{$vehicle->id}")->assertOk();

        $this->actingAsApiUser($seller);
        $this->assertDocumentRequired($seller, $vehicle->id);
        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Alterada depois da venda'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")->assertForbidden();
        $this->assertModelExists($maintenance);
    }

    public function test_account_cannot_link_a_vehicle_owned_by_another_account(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();

        $stranger = $this->actingAsApiUser();

        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", [
            'license_plate' => 'ABC1D23',
            'renavam' => self::RENAVAM,
        ])->assertForbidden();

        $this->assertDatabaseMissing('user_vehicles', [
            'user_id' => $stranger->id,
            'vehicle_id' => $vehicle->id,
            'is_current_owner' => true,
        ]);
    }

    private function assertDocumentRequired(User $account, int $vehicleId): void
    {
        $this->actingAsApiUser($account);

        $response = $this->postJson("/api/v1/vehicles/{$vehicleId}/link")->assertStatus(422);

        $this->assertStringNotContainsString(self::CHASSIS, (string) $response->getContent());
        $this->assertStringNotContainsString(self::RENAVAM, (string) $response->getContent());
        $this->assertDatabaseMissing('user_vehicles', [
            'user_id' => $account->id,
            'vehicle_id' => $vehicleId,
            'is_current_owner' => true,
        ]);
    }

    private function claimWithDocument(int $vehicleId): TestResponse
    {
        return $this->postJson("/api/v1/vehicles/{$vehicleId}/link", [
            'license_plate' => 'ABC1D23',
            'renavam' => self::RENAVAM,
        ]);
    }

    /**
     * @return array{User, Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicle();
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->refresh(), $vehicle];
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
        ]);
    }

    private function consignmentDealer(Vehicle $vehicle, string $status): User
    {
        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        VehicleAccessGrant::create([
            'user_id' => $dealer->id,
            'vehicle_id' => $vehicle->id,
            'grant_type' => 'consignment',
            'status' => $status,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        return $dealer;
    }

    private function declaredBy(User $account, Vehicle $vehicle): Maintenance
    {
        return Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $account->id,
            'tenant_id' => $account->tenant_id,
            'description' => 'Declarada',
        ]);
    }
}
