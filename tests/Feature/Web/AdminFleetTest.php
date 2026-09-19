<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFleetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
    }

    public function test_admin_routes_show_admin_nav_only_without_user_portal_links(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('id="nav-admin"', false)
            ->assertSee('Mapas', false)
            ->assertSee('Veículos', false)
            ->assertDontSee('Meus Veículos');
    }

    public function test_admin_maintenance_index_shows_provenance_markers_and_filter_labels(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        Maintenance::factory()
            ->for($vehicle)
            ->for($owner)
            ->sealedByWorkshop()
            ->create(['maintenance_type' => 'Revisão selo QA']);

        Maintenance::factory()
            ->for($vehicle)
            ->for($owner)
            ->declaredByOwner()
            ->create(['maintenance_type' => 'Troca declarada QA']);

        $this->actingAs($admin)
            ->get('/admin/manutencoes')
            ->assertOk()
            ->assertSee('Selo da oficina', false)
            ->assertSee('Declaradas', false)
            ->assertSee('prov-verified', false)
            ->assertSee('prov-declared', false)
            ->assertSee('Revisão selo QA', false)
            ->assertSee('Troca declarada QA', false);

        $this->actingAs($admin)
            ->get('/admin/manutencoes?verified=1')
            ->assertOk()
            ->assertSee('Revisão selo QA', false)
            ->assertDontSee('Troca declarada QA', false);

        $this->actingAs($admin)
            ->get('/admin/manutencoes?verified=0')
            ->assertOk()
            ->assertSee('Troca declarada QA', false)
            ->assertDontSee('Revisão selo QA', false);
    }

    public function test_admin_maintenance_index_ajax_returns_table_fragment_only(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/manutencoes?verified=1', [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertSee('data-admin-maintenances-pagination', false)
            ->assertDontSee('data-maintenance-filter', false)
            ->assertDontSee('id="nav-admin"', false);
    }

    public function test_admin_vehicle_index_lists_all_vehicles(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $ownerA = User::factory()->asUser()->create(['name' => 'Dono A']);
        $ownerB = User::factory()->asUser()->create(['name' => 'Dono B']);

        $vehicleA = Vehicle::factory()->create(['license_plate' => 'AAA1A11', 'chassis' => 'CHASSISAAA111']);
        $vehicleB = Vehicle::factory()->create(['license_plate' => 'BBB2B22', 'chassis' => 'CHASSISBBB222']);

        $ownerA->vehicles()->attach($vehicleA->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $ownerA->tenant_id,
        ]);
        $ownerB->vehicles()->attach($vehicleB->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $ownerB->tenant_id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/veiculos')
            ->assertOk()
            ->assertSee('AAA1A11')
            ->assertSee('BBB2B22')
            ->assertSee('Dono A')
            ->assertSee('Dono B');
    }

    public function test_admin_maintenance_index_and_maps(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_name' => 'Oficina Map Teste',
        ]);

        Workshop::factory()->create([
            'name' => 'Oficina Pin',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'cep' => '01310100',
        ]);

        $this->actingAs($admin)
            ->get('/admin/manutencoes')
            ->assertOk()
            ->assertSee('Oficina Map Teste');

        $this->actingAs($admin)
            ->get('/admin/mapa/oficinas')
            ->assertOk()
            ->assertSee('leaflet', false);
    }
}
