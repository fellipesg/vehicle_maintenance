<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Policies\MaintenancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Quem altera ou exclui uma manutenção: a oficina só a OS com o Selo da própria oficina; um registro
 * que o proprietário declarou citando a oficina (workshop_id, sem selo) continua de quem declarou,
 * e só enquanto essa conta for a dona atual do veículo (quem vendeu o carro perde a edição). Fotos
 * e notas fiscais seguem a mesma regra. Vale para o app (API) e para o portal web.
 */
class MaintenancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_changes_only_the_order_it_sealed(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshopUser->workshop->id]);

        $this->assertTrue($workshopUser->can('update', $sealed));
        $this->assertTrue($workshopUser->can('delete', $sealed));
    }

    public function test_workshop_cannot_change_a_declared_record_that_only_cites_it(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $declared = Maintenance::factory()->declaredByOwner()->create(['workshop_id' => $workshopUser->workshop->id]);

        $this->assertFalse($workshopUser->can('update', $declared));
        $this->assertFalse($workshopUser->can('delete', $declared));
    }

    public function test_workshop_cannot_change_an_order_sealed_by_another_workshop(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $otherWorkshop = Workshop::factory()->create();
        $sealedElsewhere = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $otherWorkshop->id]);

        $this->assertFalse($workshopUser->can('update', $sealedElsewhere));
        $this->assertFalse($workshopUser->can('delete', $sealedElsewhere));
    }

    public function test_owner_changes_only_the_records_they_declared(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $declared = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);
        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);
        $otherTenant = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $this->assertTrue($owner->can('update', $declared));
        $this->assertTrue($owner->can('delete', $declared));
        $this->assertFalse($owner->can('update', $sealed));
        $this->assertFalse($owner->can('delete', $sealed));
        $this->assertFalse($owner->can('update', $otherTenant));
        $this->assertFalse($owner->can('delete', $otherTenant));
    }

    public function test_seller_loses_the_records_they_declared_once_the_vehicle_changes_owner(): void
    {
        $seller = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($seller, $vehicle);
        $declared = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $seller->id, 'tenant_id' => $seller->tenant_id]);
        $this->assertTrue($seller->can('update', $declared));

        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);
        $buyer = User::factory()->asUser()->create();
        $this->attachVehicleToUser($buyer, $vehicle);

        $this->assertFalse($seller->can('update', $declared));
        $this->assertFalse($seller->can('delete', $declared));
        $this->assertSame(MaintenancePolicy::DENIED_NOT_CURRENT_OWNER, Gate::forUser($seller)->inspect('update', $declared)->code());
        // O comprador vê o registro do vendedor, mas não altera o que outra conta declarou.
        $this->assertTrue($buyer->can('view', $declared));
        $this->assertFalse($buyer->can('update', $declared));
        $this->assertNull(Gate::forUser($buyer)->inspect('update', $declared)->code());
    }

    public function test_dealer_holding_the_vehicle_on_consignment_cannot_change_what_it_declared(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => false,
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        $declared = Maintenance::factory()->declaredByGarage()->create(['vehicle_id' => $vehicle->id, 'user_id' => $dealer->id, 'tenant_id' => $dealer->tenant_id]);

        $this->assertFalse($dealer->can('update', $declared));
        $this->assertFalse($dealer->can('delete', $declared));
    }

    public function test_photos_and_invoices_follow_the_same_rule(): void
    {
        $seller = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($seller, $vehicle);
        $declared = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $seller->id, 'tenant_id' => $seller->tenant_id]);
        $invoice = Invoice::factory()->create(['maintenance_id' => $declared->id]);
        $photo = MaintenancePhoto::factory()->create(['maintenance_id' => $declared->id]);

        $this->assertTrue($seller->can('delete', $invoice));
        $this->assertTrue($seller->can('delete', $photo));

        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false, 'sale_date' => now()]);

        $this->assertTrue($seller->can('view', $invoice), 'Quem declarou ainda vê a nota.');
        $this->assertFalse($seller->can('delete', $invoice));
        $this->assertFalse($seller->can('delete', $photo));
    }

    public function test_owner_cannot_delete_the_invoice_of_a_sealed_order(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);
        $invoice = Invoice::factory()->create(['maintenance_id' => $sealed->id]);

        $this->assertTrue($owner->can('view', $invoice));
        $this->assertFalse($owner->can('delete', $invoice));
    }

    public function test_api_refuses_a_workshop_deleting_a_declared_record_that_cites_it(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $declared = Maintenance::factory()->declaredByOwner()->create([
            'workshop_id' => $workshopUser->workshop->id,
            'maintenance_type' => 'Revisão declarada',
        ]);

        $this->actingAsApiUser($workshopUser);

        $this->deleteJson("/api/v1/maintenances/{$declared->id}")->assertForbidden();

        $this->assertDatabaseHas('maintenances', ['id' => $declared->id, 'maintenance_type' => 'Revisão declarada']);
    }
}
