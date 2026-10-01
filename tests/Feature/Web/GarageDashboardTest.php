<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\TestCase;

/**
 * Início do Lojista: cabeçalho com as ações, KPIs que abrem o Estoque filtrado, "Prontos para
 * vender" (com Selo da oficina) e "Precisam de atenção" com a próxima ação de cada veículo.
 */
class GarageDashboardTest extends TestCase
{
    use InspectsGaragePages;
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create(['name' => 'Loja Bom Carro']);
    }

    public function test_header_has_one_heading_and_the_primary_action_last(): void
    {
        $this->stockVehicle($this->garage);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.dashboard')));

        $this->assertSingleHeading($xpath, 'Olá, Loja Bom Carro');
        $actions = $xpath->query('//*[@data-slot="page-header-actions"]//a[@data-slot="button"]');
        $this->assertSame(2, $actions->length);
        $this->assertSame(route('garage.maintenances.create'), $actions->item(0)->getAttribute('href'));
        $this->assertSame('secondary', $actions->item(0)->getAttribute('data-variant'));
        $this->assertSame(route('garage.vehicles.create'), $actions->item(1)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//*[contains(@class, "stat-card")]')->length, 'Sem ações dentro de card de KPI.');
    }

    public function test_kpis_link_to_the_filtered_stock(): void
    {
        $this->stockVehicle($this->garage);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.dashboard')));

        foreach ([
            'vehicles' => route('garage.vehicles.index'),
            'sealed-vehicles' => route('garage.vehicles.index', ['filtro' => 'selo']),
            'declared-only-vehicles' => route('garage.vehicles.index', ['filtro' => 'declaradas']),
            'without-history' => route('garage.vehicles.index', ['filtro' => 'sem-historico']),
        ] as $stat => $href) {
            $this->assertSame($href, $this->garageElement($xpath, '//a[@data-slot="stat"][@data-stat="'.$stat.'"]')->getAttribute('href'));
        }
    }

    public function test_ready_to_sell_lists_only_vehicles_with_the_workshop_seal(): void
    {
        $sealed = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $sealed->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $sealed->id]);
        $declared = $this->stockVehicle($this->garage, ['brand' => 'Fiat', 'model' => 'Argo']);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $declared->id]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.dashboard')));
        $ready = $this->garageElement($xpath, '//ul[@data-dashboard-list="ready"]');

        $this->assertSame(1, $xpath->query('.//li[@data-stock-vehicle="'.$sealed->id.'"]', $ready)->length);
        $this->assertSame(0, $xpath->query('.//li[@data-stock-vehicle="'.$declared->id.'"]', $ready)->length);
        $this->assertSame(route('garage.vehicles.show', $sealed), $this->garageElement($xpath, './/h3/a', $ready)->getAttribute('href'));
        $this->assertStringContainsString('1 com selo · 1 declarada', $this->garageText($ready));
        $this->garageElement($xpath, '//section[@id="prontos-para-vender"]//a[@href="'.route('garage.vehicles.index', ['filtro' => 'selo']).'"]');
    }

    public function test_attention_list_gives_the_next_step_for_each_vehicle(): void
    {
        $withoutHistory = $this->stockVehicle($this->garage);
        $declared = $this->stockVehicle($this->garage);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $declared->id]);
        $pending = $this->consignedStockVehicle($this->garage, 'pending');
        $rejected = $this->consignedStockVehicle($this->garage, 'rejected');

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.dashboard')));
        $attention = $this->garageElement($xpath, '//ul[@data-dashboard-list="attention"]');

        $this->assertSame(
            ['consignment_rejected', 'consignment_pending', 'without_history', 'declared_only'],
            array_map(fn ($item): string => $item->getAttribute('data-attention'), iterator_to_array($xpath->query('./li', $attention))),
        );

        $rejectedItem = $this->garageElement($xpath, './li[@data-stock-vehicle="'.$rejected->id.'"]', $attention);
        $this->assertStringContainsString('Pedido de histórico recusado', $this->garageText($rejectedItem));
        $this->assertSame(
            route('garage.vehicles.show', $rejected),
            $this->garageElement($xpath, './/a[contains(., "Pedir liberação ao proprietário")]', $rejectedItem)->getAttribute('href'),
        );

        $pendingItem = $this->garageElement($xpath, './li[@data-stock-vehicle="'.$pending->id.'"]', $attention);
        $this->assertStringContainsString('Histórico aguardando liberação', $this->garageText($pendingItem));
        // Pedido já enviado: o veículo abre, mas não há ação pendente para a loja.
        $this->assertSame(
            [route('garage.vehicles.show', $pending)],
            array_map(fn ($link): string => $link->getAttribute('href'), iterator_to_array($xpath->query('.//a', $pendingItem))),
        );

        $emptyItem = $this->garageElement($xpath, './li[@data-stock-vehicle="'.$withoutHistory->id.'"]', $attention);
        $this->assertSame(
            route('garage.maintenances.create', ['vehicle_id' => $withoutHistory->id]),
            $this->garageElement($xpath, './/a[contains(., "Registrar manutenção")]', $emptyItem)->getAttribute('href'),
        );

        $declaredItem = $this->garageElement($xpath, './li[@data-stock-vehicle="'.$declared->id.'"]', $attention);
        $this->assertStringContainsString('Leve a uma oficina da rede para ter o Selo da oficina.', $this->garageText($declaredItem));
    }

    public function test_attention_list_is_limited_and_says_how_many_are_left(): void
    {
        foreach (range(1, 7) as $index) {
            $this->stockVehicle($this->garage);
        }

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.dashboard')));

        $this->assertSame(5, $xpath->query('//ul[@data-dashboard-list="attention"]/li')->length);
        $this->assertStringContainsString('Mais 2 veículos no estoque.', $this->garageText($this->garageElement($xpath, '//section[@id="precisam-de-atencao"]')));
    }

    public function test_empty_lists_explain_the_state(): void
    {
        $sealed = $this->stockVehicle($this->garage);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $sealed->id]);

        $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('Tudo em dia')
            ->assertDontSee('data-dashboard-list="attention"', false);

        $garageWithoutSeal = User::factory()->asGarage()->create();
        $this->stockVehicle($garageWithoutSeal);

        $this->actingAs($garageWithoutSeal)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('Nenhum veículo com Selo da oficina ainda')
            ->assertSee('Nenhuma manutenção nos veículos do estoque');
    }
}
