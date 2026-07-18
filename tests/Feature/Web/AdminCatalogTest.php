<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\VehicleCatalogService;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
    }

    public function test_guest_cannot_access_admin_panel(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Painel Administrador');
    }

    public function test_admin_can_create_brand_and_model(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/marcas', [
                'name' => 'RAM',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.brands.index'));

        $brand = VehicleBrand::where('name', 'RAM')->first();
        $this->assertNotNull($brand);

        $this->actingAs($admin)
            ->post("/admin/marcas/{$brand->id}/modelos", [
                'name' => '1500',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.brands.show', $brand));

        $this->assertDatabaseHas('vehicle_models', [
            'vehicle_brand_id' => $brand->id,
            'name' => '1500',
        ]);

        $catalog = app(VehicleCatalogService::class)->all();
        $this->assertArrayHasKey('RAM', $catalog);
        $this->assertContains('1500', $catalog['RAM']);
    }

    public function test_admin_can_update_and_delete_model(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $brand = VehicleBrand::factory()->create(['name' => 'TestBrand']);
        $model = VehicleModel::factory()->create([
            'vehicle_brand_id' => $brand->id,
            'name' => 'OldModel',
        ]);

        $this->actingAs($admin)
            ->put("/admin/modelos/{$model->id}", [
                'name' => 'NewModel',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.brands.show', $brand));

        $this->assertDatabaseHas('vehicle_models', [
            'id' => $model->id,
            'name' => 'NewModel',
        ]);

        $this->actingAs($admin)
            ->delete("/admin/modelos/{$model->id}")
            ->assertRedirect(route('admin.brands.show', $brand));

        $this->assertDatabaseMissing('vehicle_models', ['id' => $model->id]);
    }

    public function test_catalog_seeder_imports_static_data(): void
    {
        $this->assertGreaterThan(20, VehicleBrand::count());
        $this->assertGreaterThan(100, VehicleModel::count());

        $catalog = app(VehicleCatalogService::class)->all();
        $this->assertArrayHasKey('Mercedes-Benz', $catalog);
        $this->assertContains('C 180', $catalog['Mercedes-Benz']);
    }
}
