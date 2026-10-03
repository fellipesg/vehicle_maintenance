<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quem altera (PUT) e exclui (DELETE) uma manutenção, pela API do app e pelas rotas da web, com a
 * mesma MaintenancePolicy: o dono atual do veículo nas declaradas da conta; a oficina só na OS com
 * o selo dela. Quem vendeu o carro, outra conta e o dono diante de uma OS com Selo da oficina
 * recebem 403, e o registro fica como estava.
 */
class MaintenanceChangeAccessTest extends TestCase
{
    use RefreshDatabase;

    private const DECLARED_KILOMETERS = 45_000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_api_current_owner_updates_and_deletes_the_declared_record(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($owner, $vehicle);

        $this->actingAsApiUser($owner);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Corrigida pelo dono'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Corrigida pelo dono');

        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")->assertOk();
        $this->assertModelMissing($maintenance);
    }

    public function test_api_seller_can_no_longer_update_or_delete_what_they_declared(): void
    {
        [$seller, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($seller, $vehicle);
        $this->sell($seller, $vehicle);

        $this->actingAsApiUser($seller);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Alterada depois da venda'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")->assertForbidden();
        $this->assertUnchanged($maintenance);
    }

    public function test_api_other_account_cannot_update_or_delete(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($owner, $vehicle);

        $this->actingAsApiUser(User::factory()->asUser()->create());

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Alterada por outra conta'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$maintenance->id}")->assertForbidden();
        $this->assertUnchanged($maintenance);
    }

    public function test_api_owner_cannot_update_or_delete_a_record_with_the_workshop_seal(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $sealed = $this->sealedOrder(Workshop::factory()->create(), $vehicle, $owner);

        $this->actingAsApiUser($owner);

        $this->putJson("/api/v1/maintenances/{$sealed->id}", ['description' => 'Alterada pelo dono'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$sealed->id}")->assertForbidden();
        $this->assertUnchanged($sealed);
    }

    public function test_api_workshop_updates_and_deletes_the_order_it_sealed(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $workshopUser = User::factory()->asWorkshop()->create();
        $order = $this->sealedOrder($workshopUser->workshop, $vehicle, $owner);

        $this->actingAsApiUser($workshopUser);

        $this->putJson("/api/v1/maintenances/{$order->id}", ['description' => 'Ajuste da oficina'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Ajuste da oficina');

        $this->deleteJson("/api/v1/maintenances/{$order->id}")->assertOk();
        $this->assertModelMissing($order);
    }

    public function test_api_workshop_cannot_update_or_delete_an_order_sealed_by_another_workshop(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $order = $this->sealedOrder(Workshop::factory()->create(), $vehicle, $owner);

        $this->actingAsApiUser(User::factory()->asWorkshop()->create());

        $this->putJson("/api/v1/maintenances/{$order->id}", ['description' => 'Alterada por outra oficina'])->assertForbidden();
        $this->deleteJson("/api/v1/maintenances/{$order->id}")->assertForbidden();
        $this->assertUnchanged($order);
    }

    public function test_web_current_owner_updates_and_deletes_the_declared_record(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($owner, $vehicle);

        $this->actingAs($owner)
            ->put(route('user.maintenances.update', $maintenance), $this->ownerPayload(['maintenance_type' => 'Revisão corrigida']))
            ->assertRedirect(route('user.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors();
        $this->assertSame('Revisão corrigida', $maintenance->fresh()->maintenance_type);

        $this->actingAs($owner)
            ->delete(route('user.maintenances.destroy', $maintenance))
            ->assertRedirect(route('user.vehicles.show', $vehicle).'#historico');
        $this->assertModelMissing($maintenance);
    }

    public function test_web_seller_cannot_open_update_or_delete_what_they_declared(): void
    {
        [$seller, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($seller, $vehicle);
        $this->sell($seller, $vehicle);

        $this->actingAs($seller)->get(route('user.maintenances.edit', $maintenance))->assertForbidden();
        $this->actingAs($seller)->put(route('user.maintenances.update', $maintenance), $this->ownerPayload())->assertForbidden();
        $this->actingAs($seller)->delete(route('user.maintenances.destroy', $maintenance))->assertForbidden();
        $this->assertUnchanged($maintenance);
    }

    public function test_web_other_account_cannot_update_or_delete(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = $this->declaredBy($owner, $vehicle);
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)->put(route('user.maintenances.update', $maintenance), $this->ownerPayload())->assertForbidden();
        $this->actingAs($stranger)->delete(route('user.maintenances.destroy', $maintenance))->assertForbidden();
        $this->assertUnchanged($maintenance);
    }

    public function test_web_owner_cannot_update_or_delete_a_record_with_the_workshop_seal(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $sealed = $this->sealedOrder(Workshop::factory()->create(), $vehicle, $owner);

        $this->actingAs($owner)->put(route('user.maintenances.update', $sealed), $this->ownerPayload())->assertForbidden();
        $this->actingAs($owner)->delete(route('user.maintenances.destroy', $sealed))->assertForbidden();
        $this->assertUnchanged($sealed);
    }

    public function test_web_workshop_updates_and_deletes_the_order_it_sealed(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $workshopUser = User::factory()->asWorkshop()->create();
        $order = $this->sealedOrder($workshopUser->workshop, $vehicle, $owner);

        $this->actingAs($workshopUser)
            ->put(route('workshop.maintenances.update', $order), $this->ownerPayload(['maintenance_type' => 'OS corrigida']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('OS corrigida', $order->fresh()->maintenance_type);

        $this->actingAs($workshopUser)
            ->delete(route('workshop.maintenances.destroy', $order))
            ->assertRedirect(route('workshop.maintenances.index'));
        $this->assertModelMissing($order);
    }

    public function test_web_workshop_cannot_update_or_delete_an_order_sealed_by_another_workshop(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $order = $this->sealedOrder(Workshop::factory()->create(), $vehicle, $owner);
        $otherWorkshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($otherWorkshopUser)->put(route('workshop.maintenances.update', $order), $this->ownerPayload())->assertForbidden();
        $this->actingAs($otherWorkshopUser)->delete(route('workshop.maintenances.destroy', $order))->assertForbidden();
        $this->assertUnchanged($order);
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        // Cadastrado hoje com 60.000 km: a manutenção de dois meses atrás fica abaixo disso.
        $vehicle = Vehicle::factory()->create([
            'odometer_at_registration' => 60_000,
            'current_kilometers' => 60_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner, $vehicle];
    }

    private function declaredBy(User $user, Vehicle $vehicle): Maintenance
    {
        return Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => null,
            'maintenance_type' => 'Revisão original',
            'description' => 'Descrição original',
            'maintenance_date' => now()->subMonths(2)->toDateString(),
            'kilometers' => self::DECLARED_KILOMETERS,
        ]);
    }

    private function sealedOrder(Workshop $workshop, Vehicle $vehicle, User $owner): Maintenance
    {
        return Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshop->user_id ?? $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'maintenance_type' => 'Revisão original',
            'description' => 'Descrição original',
            'maintenance_date' => now()->subMonths(2)->toDateString(),
            'kilometers' => self::DECLARED_KILOMETERS,
        ]);
    }

    /**
     * O vendedor deixa de ser o dono atual e o comprador entra com o veículo na conta dele.
     */
    private function sell(User $seller, Vehicle $vehicle): User
    {
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $buyer = User::factory()->asUser()->create();
        $this->attachVehicleToUser($buyer, $vehicle);

        return $buyer;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ownerPayload(array $overrides = []): array
    {
        return [
            'maintenance_type' => 'Revisão alterada',
            'service_category' => 'mechanical',
            'maintenance_date' => now()->subMonths(2)->toDateString(),
            'kilometers' => self::DECLARED_KILOMETERS + 500,
            ...$overrides,
        ];
    }

    private function assertUnchanged(Maintenance $maintenance): void
    {
        $fresh = $maintenance->fresh();

        $this->assertNotNull($fresh, 'O registro não pode ter sido excluído.');
        $this->assertSame('Revisão original', $fresh->maintenance_type);
        $this->assertSame('Descrição original', $fresh->description);
        $this->assertSame(self::DECLARED_KILOMETERS, $fresh->kilometers);
    }
}
