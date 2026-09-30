<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fora da busca, a API também devolve o veículo (VehicleResource) para quem não é o dono atual:
 * lojista em consignação, oficina, dono anterior, admin. Para eles chassi e RENAVAM saem parciais,
 * com as mesmas chaves e identifiers_masked = true; o dono atual recebe os números inteiros e
 * identifiers_masked = false (App\Support\Vehicle\VehicleIdentifierVisibility).
 */
class VehicleIdentifierMaskingApiTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const MASKED_CHASSIS = '9BW••••••••••4251';

    private const MASKED_RENAVAM = '•••••••8901';

    public function test_owner_gets_the_full_numbers_on_the_vehicle_detail(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->actingAsApiUser($owner);

        $this->assertFullIdentifiers($this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk(), 'data');
    }

    public function test_dealer_holding_the_vehicle_on_consignment_gets_masked_numbers(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
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
            'status' => 'approved',
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        $this->actingAsApiUser($dealer);

        $this->assertMaskedIdentifiers($this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertOk(), 'data');
    }

    public function test_seller_sees_masked_numbers_in_the_records_they_declared_before_the_sale(): void
    {
        [$seller, $vehicle] = $this->ownerWithVehicle();
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $seller->id,
            'tenant_id' => $seller->tenant_id,
        ]);
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        $this->actingAsApiUser($seller);

        $this->assertMaskedIdentifiers($this->getJson("/api/v1/maintenances/{$maintenance->id}")->assertOk(), 'data.vehicle');
        $this->assertMaskedIdentifiers($this->getJson('/api/v1/maintenances')->assertOk(), 'data.0.vehicle');
    }

    public function test_owner_gets_the_full_numbers_in_the_maintenance_list_and_detail(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenances = Maintenance::factory()->count(3)->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->actingAsApiUser($owner);

        $this->assertFullIdentifiers($this->getJson("/api/v1/maintenances/{$maintenances[0]->id}")->assertOk(), 'data.vehicle');

        $list = $this->getJson('/api/v1/maintenances')->assertOk()->assertJsonCount(3, 'data');
        foreach (range(0, 2) as $index) {
            $this->assertFullIdentifiers($list, "data.{$index}.vehicle");
        }
    }

    public function test_workshop_gets_masked_numbers_on_the_order_it_sealed(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $workshopUser = User::factory()->asWorkshop()->create();
        $order = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshopUser->workshop->id,
        ]);

        $this->actingAsApiUser($workshopUser);

        $this->assertMaskedIdentifiers($this->getJson("/api/v1/maintenances/{$order->id}")->assertOk(), 'data.vehicle');
        $this->assertMaskedIdentifiers($this->getJson('/api/v1/maintenances')->assertOk(), 'data.0.vehicle');
    }

    public function test_admin_list_masks_vehicles_the_admin_does_not_own(): void
    {
        $this->ownerWithVehicle();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAsApiUser($admin);

        $this->assertMaskedIdentifiers($this->getJson('/api/v1/admin/vehicles')->assertOk(), 'data.0');
    }

    public function test_my_vehicles_me_and_login_keep_the_full_numbers_for_the_owner(): void
    {
        [$owner] = $this->ownerWithVehicle(['email' => 'dono@example.com', 'password' => bcrypt('senha-de-teste')]);

        $this->assertFullIdentifiers(
            $this->postJson('/api/v1/login', ['email' => 'dono@example.com', 'password' => 'senha-de-teste'])->assertOk(),
            'data.user.vehicles.0',
        );

        $this->actingAsApiUser($owner);

        $this->assertFullIdentifiers($this->getJson('/api/v1/my-vehicles')->assertOk(), 'data.0');
        $this->assertFullIdentifiers($this->getJson('/api/v1/me')->assertOk(), 'data.vehicles.0');
    }

    /**
     * @param  array<string, mixed>  $ownerAttributes
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(array $ownerAttributes = []): array
    {
        $owner = User::factory()->asUser()->create($ownerAttributes);
        $vehicle = Vehicle::factory()->create(['chassis' => self::CHASSIS, 'renavam' => self::RENAVAM]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner, $vehicle];
    }

    private function assertFullIdentifiers(TestResponse $response, string $path): void
    {
        $response
            ->assertJsonPath("{$path}.chassis", self::CHASSIS)
            ->assertJsonPath("{$path}.renavam", self::RENAVAM)
            ->assertJsonPath("{$path}.identifiers_masked", false);
    }

    private function assertMaskedIdentifiers(TestResponse $response, string $path): void
    {
        $response
            ->assertJsonPath("{$path}.chassis", self::MASKED_CHASSIS)
            ->assertJsonPath("{$path}.renavam", self::MASKED_RENAVAM)
            ->assertJsonPath("{$path}.identifiers_masked", true);

        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString(self::CHASSIS, $payload);
        $this->assertStringNotContainsString(self::RENAVAM, $payload);
    }
}
