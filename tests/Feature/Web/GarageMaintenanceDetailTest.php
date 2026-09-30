<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\TestCase;

/**
 * Detalhe da manutenção no Lojista (garage.maintenances.show, com maintenances._detail) e os links
 * da lista de manutenções para ele. Só abre manutenção de veículo do estoque com histórico liberado.
 */
class GarageMaintenanceDetailTest extends TestCase
{
    use InspectsGaragePages;
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_detail_page_has_one_heading_trail_and_the_shared_body(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Troca de correia dentada',
            'maintenance_date' => '2024-05-10',
            'kilometers' => 42_000,
        ]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.show', $maintenance)));

        $this->assertSingleHeading($xpath, 'Troca de correia dentada');
        $this->assertSame('Honda Civic · CIV1C23 · 10/05/2024', $this->garageText($this->garageElement($xpath, '//*[@data-slot="page-header-description"]')));
        $this->assertSame(
            [['Estoque', route('garage.vehicles.index')], ['Honda Civic', route('garage.vehicles.show', $vehicle)], ['Troca de correia dentada', null]],
            $this->garageTrail($xpath),
        );

        $detail = $this->garageElement($xpath, '//*[@data-slot="maintenance-detail"][@data-maintenance-id="'.$maintenance->id.'"]');
        $this->assertStringContainsString('Selo da oficina', $this->garageText($detail));
        $this->assertStringContainsString('42.000 km', $this->garageText($detail));
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('garage.vehicles.show', $vehicle).'"]', $detail)->length);
        $this->assertSame(
            route('garage.maintenances.create', ['vehicle_id' => $vehicle->id]),
            $this->garageElement($xpath, '//*[@data-slot="page-header-actions"]//a')->getAttribute('href'),
        );
        $this->assertStringContainsString('<title>Troca de correia dentada · Lojista · RevisaLog</title>', $this->actingAs($this->garage)->get(route('garage.maintenances.show', $maintenance))->getContent());
    }

    public function test_previous_owner_record_of_a_stock_vehicle_is_visible(): void
    {
        $vehicle = $this->stockVehicle($this->garage);
        $previousOwner = User::factory()->asUser()->create();
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $previousOwner->id,
            'maintenance_type' => 'Troca de pastilhas',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Troca de pastilhas')
            ->assertSee('Declarada pelo proprietário');
    }

    public function test_approved_consignment_opens_the_detail_without_register_action(): void
    {
        $vehicle = $this->consignedStockVehicle($this->garage, 'approved');
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.show', $maintenance)));

        $this->assertSame(0, $xpath->query('//*[@data-slot="page-header-actions"]')->length);
    }

    public function test_detail_is_forbidden_outside_the_visible_stock_history(): void
    {
        $foreign = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => Vehicle::factory()->create()->id]);
        $pending = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $this->consignedStockVehicle($this->garage, 'pending')->id]);

        $sold = Vehicle::factory()->create();
        $this->garage->vehicles()->attach($sold->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYear(),
            'tenant_id' => $this->garage->tenant_id,
        ]);
        $ownOnSold = Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $sold->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
        ]);

        foreach ([$foreign, $pending, $ownOnSold] as $maintenance) {
            $this->actingAs($this->garage)->get(route('garage.maintenances.show', $maintenance))->assertForbidden();
        }
    }

    public function test_other_portals_cannot_open_the_garage_detail(): void
    {
        $vehicle = $this->stockVehicle($this->garage);
        $maintenance = Maintenance::factory()->declaredByGarage()->create(['vehicle_id' => $vehicle->id, 'user_id' => $this->garage->id]);

        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('garage.maintenances.show', $maintenance))
            ->assertRedirect(route('user.dashboard'));

        auth()->logout();

        $this->get(route('garage.maintenances.show', $maintenance))->assertRedirect(route('login.lojista'));
    }

    public function test_list_links_stock_records_to_the_detail_and_leaves_sold_vehicles_without_link(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);
        $stockRecord = Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Revisão pré-venda',
        ]);

        $sold = Vehicle::factory()->create(['brand' => 'Chevrolet', 'model' => 'Onix']);
        $this->garage->vehicles()->attach($sold->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYear(),
            'tenant_id' => $this->garage->tenant_id,
        ]);
        $soldRecord = Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $sold->id,
            'user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'maintenance_type' => 'Revisão de carro vendido',
        ]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.index', ['filtro' => 'minhas'])));

        $this->assertSingleHeading($xpath, 'Manutenções');
        $this->assertSame(1, $xpath->query('//a[@href="'.route('garage.maintenances.show', $stockRecord).'"]')->length);
        $this->assertSame(0, $xpath->query('//a[@href="'.route('garage.maintenances.show', $soldRecord).'"]')->length);
        $this->garageElement($xpath, '//*[@data-maintenance-card="'.$soldRecord->id.'"]');
    }

    public function test_list_filters_by_stock_vehicle_and_keeps_the_provenance_filter(): void
    {
        $civic = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);
        $onix = $this->stockVehicle($this->garage, ['brand' => 'Chevrolet', 'model' => 'Onix']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $civic->id, 'maintenance_type' => 'Selo no Civic']);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $civic->id, 'maintenance_type' => 'Declarada no Civic']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $onix->id, 'maintenance_type' => 'Selo no Onix']);

        $response = $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index', ['filtro' => 'selo', 'veiculo' => $civic->id]))
            ->assertOk()
            ->assertSee('Selo no Civic')
            ->assertDontSee('Declarada no Civic')
            ->assertDontSee('Selo no Onix')
            ->assertViewHas('filters', fn (array $filters): bool => $filters['todas']['count'] === 2 && $filters['selo']['count'] === 1);

        $xpath = $this->garagePage($response);
        $this->assertSame((string) $civic->id, $this->garageElement($xpath, '//select[@name="veiculo"]/option[@selected]')->getAttribute('value'));
        $this->assertStringContainsString('veiculo='.$civic->id, $this->garageElement($xpath, '//nav[@aria-label="Filtrar manutenções"]//a[@data-maintenance-filter="declaradas"]')->getAttribute('href'));
    }

    public function test_empty_list_offers_to_register_a_maintenance(): void
    {
        $this->stockVehicle($this->garage);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.index'))
            ->assertOk()
            ->assertSee('data-maintenance-list-empty="all"', false)
            ->assertSee('Nenhuma manutenção nos veículos do estoque')
            ->assertSee('Declarada pelo lojista');
    }
}
