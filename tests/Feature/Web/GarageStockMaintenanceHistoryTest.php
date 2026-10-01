<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A lista de manutenções e o dashboard do lojista mostram o histórico dos veículos do estoque
 * (Selo da oficina e declaradas por proprietários), não só o que o lojista registrou,
 * e os KPIs usam os totais reais, não o tamanho da lista de recentes.
 */
class GarageStockMaintenanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_list_shows_workshop_seal_and_previous_owner_records_of_stock_vehicles(): void
    {
        $previousOwner = User::factory()->asUser()->create();
        $vehicle = $this->ownedVehicle(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);

        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $previousOwner->id,
            'maintenance_type' => 'Troca de correia dentada',
            'maintenance_date' => '2024-05-10',
            'kilometers' => 42000,
        ]);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $previousOwner->id,
            'maintenance_type' => 'Troca de pastilhas',
            'maintenance_date' => '2023-02-01',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index'))
            ->assertOk()
            ->assertSee('Troca de correia dentada')
            ->assertSee('Selo da oficina')
            ->assertSee('Troca de pastilhas')
            ->assertSee('Declarada pelo proprietário')
            ->assertSee('Honda Civic')
            ->assertSee('CIV1C23')
            ->assertSee('10/05/2024')
            ->assertSee('42.000 km')
            ->assertSee('href="'.route('garage.vehicles.show', $vehicle).'"', false);
    }

    public function test_dashboard_recent_maintenances_open_the_detail_and_link_the_vehicle(): void
    {
        $vehicle = $this->ownedVehicle(['brand' => 'Honda', 'model' => 'Fit']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Troca de embreagem',
        ]);

        $html = $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('garage.maintenances.index').'"', false)
            ->assertSee('Ver todas')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('garage.maintenances.show', $maintenance), '#').'"[^>]*>Troca de embreagem</a>#',
            $html,
        );
        $this->assertStringContainsString('href="'.route('garage.vehicles.show', $vehicle).'"', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('garage.vehicles.index', ['filtro' => 'selo']), '#').'"[^>]*data-slot="link"[^>]*>Ver todos#', $html);
    }

    public function test_dashboard_without_stock_has_no_see_all_link(): void
    {
        $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('Seu estoque está vazio')
            ->assertSee('data-dashboard-empty', false)
            ->assertDontSee('Ver estoque')
            ->assertDontSee('Prontos para vender');
    }

    public function test_list_respects_consignment_approval_and_other_tenants(): void
    {
        $approved = $this->consignedVehicle('approved');
        $pending = $this->consignedVehicle('pending');
        $foreign = Vehicle::factory()->create();

        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $approved->id, 'maintenance_type' => 'Revisão aprovada liberada']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $pending->id, 'maintenance_type' => 'Revisão em análise oculta']);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $foreign->id, 'maintenance_type' => 'Revisão de outro tenant']);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index'))
            ->assertOk()
            ->assertSee('Revisão aprovada liberada')
            ->assertDontSee('Revisão em análise oculta')
            ->assertDontSee('Revisão de outro tenant');
    }

    public function test_filters_split_seal_declared_and_own_records(): void
    {
        $vehicle = $this->ownedVehicle();
        $soldVehicle = Vehicle::factory()->create(['brand' => 'Chevrolet', 'model' => 'Onix']);
        $this->garage->vehicles()->attach($soldVehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYear(),
            'tenant_id' => $this->garage->tenant_id,
        ]);

        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Serviço com selo']);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Serviço declarado pelo dono']);
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Revisão pré-venda da loja',
        ]);
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $soldVehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Revisão de carro vendido',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index'))
            ->assertOk()
            ->assertSee('Serviço com selo')
            ->assertSee('Serviço declarado pelo dono')
            ->assertSee('Revisão pré-venda da loja')
            ->assertDontSee('Revisão de carro vendido')
            ->assertSee('aria-current="page"', false);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'selo']))
            ->assertOk()
            ->assertSee('Serviço com selo')
            ->assertDontSee('Serviço declarado pelo dono')
            ->assertDontSee('Revisão pré-venda da loja');

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'declaradas']))
            ->assertOk()
            ->assertDontSee('Serviço com selo')
            ->assertSee('Serviço declarado pelo dono')
            ->assertSee('Revisão pré-venda da loja');

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'minhas']))
            ->assertOk()
            ->assertDontSee('Serviço com selo')
            ->assertDontSee('Serviço declarado pelo dono')
            ->assertSee('Revisão pré-venda da loja')
            ->assertSee('Revisão de carro vendido')
            ->assertDontSee('href="'.route('garage.vehicles.show', $soldVehicle).'"', false);
    }

    public function test_own_filter_ignores_workshop_seal_and_teammates_in_the_same_tenant(): void
    {
        $vehicle = $this->ownedVehicle();
        $workshopUser = User::factory()->asWorkshop()->create();
        $teammate = User::factory()->asGarage()->create(['tenant_id' => $this->garage->tenant_id]);

        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Selo no meu tenant',
        ]);
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $teammate->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Registro de outro usuário da loja',
        ]);
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Registro meu',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'minhas']))
            ->assertOk()
            ->assertSee('Registro meu')
            ->assertDontSee('Selo no meu tenant')
            ->assertDontSee('Registro de outro usuário da loja')
            ->assertViewHas('filters', fn (array $filters): bool => $filters['minhas']['count'] === 1
                && $filters['selo']['count'] === 1
                && $filters['todas']['count'] === 3);
    }

    public function test_unknown_filter_falls_back_to_all_stock_records(): void
    {
        $vehicle = $this->ownedVehicle();
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Serviço com selo']);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'qualquer']))
            ->assertOk()
            ->assertSee('Serviço com selo')
            ->assertViewHas('filter', 'todas');
    }

    public function test_empty_filter_offers_way_back_to_all_records(): void
    {
        $vehicle = $this->ownedVehicle();
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'selo']))
            ->assertOk()
            ->assertSee('data-maintenance-list-empty="filtered"', false)
            ->assertSee('Nenhuma manutenção neste filtro')
            ->assertSee('Limpar filtros')
            ->assertSee('href="'.route('garage.maintenances.index').'"', false);
    }

    public function test_pagination_keeps_the_active_filter(): void
    {
        $vehicle = $this->ownedVehicle();
        Maintenance::factory()->count(16)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'selo']))
            ->assertOk()
            ->assertSee('filtro=selo&amp;page=2', false);
    }

    public function test_dashboard_kpis_are_real_totals_of_the_stock_history(): void
    {
        $vehicle = $this->ownedVehicle();
        $this->ownedVehicle();
        $pending = $this->consignedVehicle('pending');
        $foreign = Vehicle::factory()->create();

        Maintenance::factory()->count(4)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->count(3)->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->count(2)->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
        ]);
        Maintenance::factory()->count(2)->sealedByWorkshop()->create(['vehicle_id' => $pending->id]);
        Maintenance::factory()->count(5)->declaredByOwner()->create(['vehicle_id' => $foreign->id]);

        $html = $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('1 em consignação')
            ->assertSee('4 manutenções com Selo da oficina')
            ->assertSee('5 manutenções declaradas')
            ->assertViewHas('stats', fn (array $stats): bool => $stats['vehicles'] === 3
                && $stats['consignment'] === 1
                && $stats['sealed'] === 4
                && $stats['declared'] === 5
                && $stats['sealed_vehicles'] === 1
                && $stats['declared_only_vehicles'] === 0
                && $stats['without_history'] === 1)
            ->assertViewHas('recentMaintenances', fn ($recent) => $recent->count() === 5
                && $recent->every(fn (Maintenance $maintenance) => $maintenance->vehicle_id === $vehicle->id))
            ->getContent();

        $this->assertSame('3', $this->statValue($html, 'vehicles'));
        $this->assertSame('1', $this->statValue($html, 'sealed-vehicles'));
        $this->assertSame('0', $this->statValue($html, 'declared-only-vehicles'));
        $this->assertSame('1', $this->statValue($html, 'without-history'));
    }

    public function test_dashboard_recent_list_includes_workshop_seal_from_previous_owner(): void
    {
        $vehicle = $this->ownedVehicle();
        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Alinhamento com selo',
            'maintenance_date' => now()->subDay(),
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('Alinhamento com selo')
            ->assertSee('Selo da oficina')
            ->assertSee('Ver todas');
    }

    private function statValue(string $html, string $stat): ?string
    {
        return preg_match('/data-stat="'.preg_quote($stat, '/').'".*?data-slot="stat-value"[^>]*>\s*([\d.]+)\s*</s', $html, $match) === 1
            ? $match[1]
            : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownedVehicle(array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes);
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
        ]);

        return $vehicle;
    }

    private function consignedVehicle(string $status): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'history_access_status' => $status,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        return $vehicle;
    }
}
