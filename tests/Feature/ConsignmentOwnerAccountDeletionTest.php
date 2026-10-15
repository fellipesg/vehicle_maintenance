<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Notifications\ConsignmentOwnerAccountDeletedNotification;
use App\Services\User\DeleteUserAccount;
use App\Services\Vehicle\VehicleConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O dono de um carro consignado exclui a conta.
 *
 * O carro continua no pátio da loja, então a consignação continua ativa e a loja segue
 * registrando serviço — é esse histórico que valoriza o veículo na venda. O que tem de sumir é a
 * pessoa: o contato que a loja declarou (`vehicle_consignments`) é cópia da PII e precisa ser
 * apagado junto com a linha de `users`, senão a exclusão promete mais do que cumpre.
 */
class ConsignmentOwnerAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $garage;

    private Vehicle $vehicle;

    private VehicleConsignment $consignment;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->asUser()->create([
            'name' => 'Maria Proprietária',
            'email' => 'maria@example.com',
            'phone' => '43999998888',
        ]);

        $this->garage = User::factory()->asGarage()->create();

        $this->vehicle = Vehicle::factory()->create([
            'current_kilometers' => 60_000,
            'odometer_at_registration' => 55_000,
        ]);

        $this->owner->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now()->subYears(2),
            'tenant_id' => $this->owner->tenant_id,
        ]);

        $this->garage->vehicles()->attach($this->vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        $this->consignment = app(VehicleConsignmentService::class)->start($this->garage, $this->vehicle, [
            'owner_name' => 'Maria Proprietária',
            'owner_email' => 'maria@example.com',
            'owner_phone' => '43999998888',
            'owner_document' => '12345678901',
        ]);
    }

    private function deleteOwnerAccount(): void
    {
        app(DeleteUserAccount::class)->handle($this->owner);
    }

    public function test_the_consignment_stays_active_and_linked_to_the_garage(): void
    {
        $this->deleteOwnerAccount();

        $this->consignment->refresh();

        $this->assertSame(VehicleConsignment::STATUS_ACTIVE, $this->consignment->status);
        $this->assertTrue($this->consignment->isActive());
        $this->assertNull($this->consignment->ended_at);
        $this->assertSame($this->garage->id, $this->consignment->garage_user_id);
        $this->assertSame($this->vehicle->id, $this->consignment->vehicle_id);
    }

    public function test_the_garage_keeps_the_vehicle_in_stock(): void
    {
        $this->deleteOwnerAccount();

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $this->garage->id,
            'vehicle_id' => $this->vehicle->id,
            'ownership_type' => 'consignment',
        ]);

        // Só o vínculo do dono sai.
        $this->assertDatabaseMissing('user_vehicles', [
            'user_id' => $this->owner->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
    }

    public function test_the_garage_can_still_register_a_maintenance(): void
    {
        $this->deleteOwnerAccount();

        $response = $this->actingAs($this->garage)->post(route('garage.maintenances.store'), [
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Revisão pré-venda',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 61_000,
            'service_category' => 'mechanical',
            'is_manufacturer_required' => '0',
        ]);

        $response->assertSessionHasNoErrors();

        $maintenance = Maintenance::query()->where('maintenance_type', 'Revisão pré-venda')->firstOrFail();

        $this->assertSame($this->vehicle->id, $maintenance->vehicle_id);
        $this->assertSame($this->garage->id, $maintenance->user_id);
        $this->assertSame('garage', $maintenance->registered_by_type);
    }

    public function test_the_history_registered_before_the_deletion_survives(): void
    {
        $previous = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
        ]);

        $ownerRecord = Maintenance::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->owner->id,
            'tenant_id' => $this->owner->tenant_id,
        ]);

        $this->deleteOwnerAccount();

        $this->assertModelExists($this->vehicle->fresh());
        $this->assertModelExists($previous->fresh());
        $this->assertModelExists($ownerRecord->fresh());
        $this->assertSame($this->vehicle->id, $ownerRecord->fresh()->vehicle_id);
    }

    public function test_the_owner_contact_is_erased_from_the_consignment(): void
    {
        $this->deleteOwnerAccount();

        $this->consignment->refresh();

        $this->assertSame(DeleteUserAccount::DELETED_NAME, $this->consignment->owner_name);
        $this->assertNull($this->consignment->owner_email);
        $this->assertNull($this->consignment->owner_phone);
        $this->assertNull($this->consignment->owner_document);

        // O vínculo com a conta fica: é por ele que a loja sabe que o dono era da plataforma.
        $this->assertSame($this->owner->id, $this->consignment->owner_user_id);

        $this->assertDatabaseMissing('vehicle_consignments', ['owner_email' => 'maria@example.com']);
        $this->assertDatabaseMissing('vehicle_consignments', ['owner_phone' => '43999998888']);
        $this->assertDatabaseMissing('vehicle_consignments', ['owner_document' => '12345678901']);
    }

    public function test_a_consignment_declared_before_the_owner_had_an_account_is_erased_too(): void
    {
        $orphan = VehicleConsignment::query()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'garage_user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'owner_user_id' => null,
            'owner_name' => 'Maria Proprietária',
            'owner_email' => 'maria@example.com',
            'owner_phone' => '43999998888',
            'declaration_accepted_at' => now(),
            'status' => VehicleConsignment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->deleteOwnerAccount();

        $orphan->refresh();

        $this->assertSame(DeleteUserAccount::DELETED_NAME, $orphan->owner_name);
        $this->assertNull($orphan->owner_email);
        $this->assertNull($orphan->owner_phone);
    }

    public function test_the_individual_tenant_loses_the_person_name(): void
    {
        $ownerTenantId = $this->owner->tenant_id;
        $garageTenantId = $this->garage->tenant_id;
        $garageTenantName = Tenant::findOrFail($garageTenantId)->name;

        $this->deleteOwnerAccount();

        $this->assertSame(DeleteUserAccount::DELETED_NAME, Tenant::findOrFail($ownerTenantId)->name);

        // Tenant de loja guarda o nome da empresa, não o de quem a cadastrou.
        $this->assertSame($garageTenantName, Tenant::findOrFail($garageTenantId)->name);
    }

    public function test_the_garage_is_told_that_the_owner_left(): void
    {
        $this->deleteOwnerAccount();

        Notification::assertSentTo(
            $this->garage,
            ConsignmentOwnerAccountDeletedNotification::class,
            function (ConsignmentOwnerAccountDeletedNotification $notification): bool {
                return $notification->consignment->id === $this->consignment->id;
            }
        );
    }

    public function test_the_notification_does_not_repeat_the_erased_contact(): void
    {
        $this->deleteOwnerAccount();

        $notification = new ConsignmentOwnerAccountDeletedNotification($this->consignment->fresh());
        $mail = $notification->toMail($this->garage);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines)).' '.$mail->subject;

        $this->assertStringNotContainsString('maria@example.com', $body);
        $this->assertStringNotContainsString('43999998888', $body);
        $this->assertStringNotContainsString('Maria', $body);
        $this->assertStringContainsString('consignação continua ativa', $body);
    }

    public function test_the_owner_account_itself_is_anonymized(): void
    {
        $this->deleteOwnerAccount();

        $this->owner->refresh();

        $this->assertSame(DeleteUserAccount::DELETED_NAME, $this->owner->name);
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $this->owner->email);
        $this->assertNull($this->owner->phone);
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }
}
