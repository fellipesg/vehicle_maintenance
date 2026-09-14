<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopMaintenanceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_can_create_maintenance_by_license_plate_and_see_it_in_index(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'QOS6H54',
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $this->assertNotNull($workshop);

        $response = $this->actingAs($workshopUser)
            ->post('/oficina/manutencoes', [
                'license_plate' => 'QOS6H54',
                'maintenance_type' => 'Revisão geral',
                'maintenance_date' => '2026-01-10',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $maintenance = Maintenance::first();
        $this->assertNotNull($maintenance);
        $this->assertSame($workshop->id, $maintenance->workshop_id);
        $this->assertSame($owner->tenant_id, $maintenance->tenant_id);

        $this->actingAs($workshopUser)
            ->get('/oficina/manutencoes')
            ->assertOk()
            ->assertSee('Revisão geral')
            ->assertSee('QOS6H54');
    }

    public function test_other_workshop_cannot_edit_maintenance(): void
    {
        $workshopA = Workshop::factory()->create();
        $workshopBUser = User::factory()->asWorkshop()->create();
        Workshop::factory()->forUser($workshopBUser)->create();

        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshopA->id]);

        $this->actingAs($workshopBUser)
            ->get("/oficina/manutencoes/{$maintenance->id}/editar")
            ->assertForbidden();
    }

    public function test_owner_still_sees_workshop_maintenance(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $workshop = Workshop::factory()->create(['name' => 'Oficina Parceira']);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => 'Oficina Parceira',
        ]);

        $this->actingAs($owner)
            ->get("/usuario/manutencoes/{$maintenance->id}")
            ->assertOk()
            ->assertSee('Oficina Parceira');
    }

    public function test_workshop_api_lists_only_own_maintenances(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $otherWorkshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create();

        Maintenance::factory()->create(['vehicle_id' => $vehicle->id, 'workshop_id' => $workshop->id]);
        Maintenance::factory()->create(['vehicle_id' => $vehicle->id, 'workshop_id' => $otherWorkshop->id]);

        $this->actingAsApiUser($workshopUser);

        $this->getJson('/api/v1/maintenances')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_workshop_can_view_maintenance_details_page(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'maintenance_type' => 'Troca de óleo',
        ]);

        $this->actingAs($workshopUser)
            ->get(route('workshop.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Troca de óleo')
            ->assertSee('ABC1D23')
            ->assertSee('Editar');
    }

    public function test_workshop_can_create_maintenance_with_warranty_items(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'XYZ9Z99',
            'current_kilometers' => 20000,
            'odometer_at_registration' => 20000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $itemTemplate = WarrantyTemplate::factory()->forWorkshop($workshop)->itemScope()->create([
            'duration_days' => 365,
        ]);

        $response = $this->actingAs($workshopUser)
            ->post('/oficina/manutencoes', [
                'license_plate' => 'XYZ9Z99',
                'maintenance_type' => 'Freios',
                'maintenance_date' => '2026-02-01',
                'kilometers' => 20000,
                'service_category' => 'mechanical',
                'items' => [
                    [
                        'name' => 'Pastilha de freio',
                        'quantity' => 2,
                        'unit_price' => 150,
                        'warranty_template_id' => $itemTemplate->id,
                    ],
                ],
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $maintenance = Maintenance::first();
        $this->assertNotNull($maintenance);
        $this->assertCount(1, $maintenance->items);

        $item = $maintenance->items->first();
        $this->assertNotNull($item->warranty);
        $this->assertSame('2026-02-01', $item->warranty->starts_at->format('Y-m-d'));
        $this->assertSame('2027-02-01', $item->warranty->ends_at->format('Y-m-d'));

        $this->actingAs($workshopUser)
            ->get(route('workshop.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Pastilha de freio')
            ->assertSee('Em garantia')
            ->assertSee('Garantia até 01/02/2027');
    }

    public function test_owner_sees_warranty_on_maintenance_details(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_date' => '2026-01-01',
        ]);

        $item = $maintenance->items()->create([
            'name' => 'Bateria 60Ah',
            'quantity' => 1,
            'unit_price' => 450,
            'total_price' => 450,
        ]);
        MaintenanceWarranty::factory()->itemScope($item)->create([
            'maintenance_id' => $maintenance->id,
            'duration_days' => 730,
        ]);

        $this->actingAs($owner)
            ->get("/usuario/manutencoes/{$maintenance->id}")
            ->assertOk()
            ->assertSee('Bateria 60Ah')
            ->assertSee('Em garantia')
            ->assertSee('Garantia até 01/01/2028');
    }
}
