<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O portal do proprietário é renderizado no servidor, com o mesmo escopo da API (que o app
 * Flutter continua usando). Estes testes travam o que as listas dependem: KPIs com os totais (não
 * o tamanho da lista de 5) e a linha de manutenção com veículo, data, km e oficina.
 */
class UserPortalOwnerListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_kpi_is_the_total_of_registered_maintenances(): void
    {
        $user = User::factory()->asUser()->create()->refresh();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        Maintenance::factory()->count(7)->create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'vehicle_id' => $vehicle->id]);

        $html = $this->actingAs($user)
            ->get('/usuario/dashboard')
            ->assertOk()
            ->assertSee('data-stat="maintenances"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#data-stat="maintenances".*?data-slot="stat-value"[^>]*>7</dd>#s', $html);
        $this->assertStringNotContainsString('data-api-page', $html);
    }

    public function test_maintenance_api_returns_totals_and_the_row_details_the_portal_shows(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->count(6)->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'vehicle_id' => $vehicle->id,
            'workshop_id' => null,
            'workshop_name' => 'Oficina do Bairro',
            'kilometers' => 42000,
            'maintenance_date' => '2025-03-10',
        ]);

        $this->getJson('/api/v1/maintenances?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.vehicle.brand', 'Honda')
            ->assertJsonPath('data.0.vehicle.model', 'Civic')
            ->assertJsonPath('data.0.vehicle.license_plate', 'CIV1C23')
            ->assertJsonPath('data.0.maintenance_date', '2025-03-10')
            ->assertJsonPath('data.0.kilometers', 42000)
            ->assertJsonPath('data.0.workshop_name', 'Oficina do Bairro');

        $this->getJson('/api/v1/maintenances?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/my-vehicles?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_maintenance_list_page_shows_the_vehicle_and_is_paginated_on_the_server(): void
    {
        $user = User::factory()->asUser()->create()->refresh();
        $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        $this->attachVehicleToUser($user, $vehicle);
        Maintenance::factory()->count(16)->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'vehicle_id' => $vehicle->id,
            'workshop_id' => null,
            'workshop_name' => 'Oficina do Bairro',
        ]);

        $this->actingAs($user)
            ->get('/usuario/manutencoes')
            ->assertOk()
            ->assertSee('Honda Civic')
            ->assertSee('CIV1C23')
            ->assertSee('Oficina do Bairro')
            ->assertSee('page=2', false)
            ->assertDontSee('data-maintenances-more', false);
    }
}
