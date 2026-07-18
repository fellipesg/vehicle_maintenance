<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
    }

    public function test_admin_dashboard_lists_users(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $owner = User::factory()->asUser()->create(['name' => 'João Proprietário']);
        $garage = User::factory()->asGarage()->create(['name' => 'Loja ABC']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Todos os usuários')
            ->assertSee('João Proprietário')
            ->assertSee('Loja ABC')
            ->assertSee('Lojista');
    }

    public function test_admin_can_view_user_vehicles_and_maintenances(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'license_plate' => 'ABC1D23',
        ]);

        $owner->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'preventive',
            'description' => 'Troca de óleo',
            'workshop_name' => 'Oficina Teste',
        ]);

        $this->actingAs($admin)
            ->get("/admin/usuarios/{$owner->id}")
            ->assertOk()
            ->assertSee('Toyota Corolla')
            ->assertSee('ABC1D23')
            ->assertSee('Troca de óleo')
            ->assertSee('Oficina Teste');
    }
}
