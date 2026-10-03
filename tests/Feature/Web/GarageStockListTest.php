<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePlate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\TestCase;

/**
 * Estoque do Lojista: busca, filtros de procedência, ordenação, Cards | Tabela, paginação, card
 * inteiro como link e estados vazios.
 */
class GarageStockListTest extends TestCase
{
    use InspectsGaragePages;
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_stock_page_has_one_heading_the_primary_action_and_the_toolbar(): void
    {
        $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.index')));

        $this->assertSingleHeading($xpath, 'Estoque');
        $this->assertSame(
            route('garage.vehicles.create'),
            $this->garageElement($xpath, '//*[@data-slot="page-header-actions"]//a[@data-slot="button"]')->getAttribute('href'),
        );
        $this->garageElement($xpath, '//form[@role="search"]//input[@name="busca"][@type="search"]');
        $this->garageElement($xpath, '//form[@role="search"]//select[@name="ordem"]');
        $this->assertSame(
            ['Todos', 'Com selo', 'Só declaradas', 'Sem histórico', 'Consignação'],
            array_map(
                fn ($link): string => trim((string) preg_replace('/\s+\d+$/', '', $this->garageText($link))),
                iterator_to_array($xpath->query('//nav[@aria-label="Filtrar o estoque"]//a')),
            ),
        );
        $this->garageElement($xpath, '//nav[@aria-label="Visualização"]//a[@aria-current="page"][contains(., "Cards")]');
        $this->assertSame('1 veículo no estoque', $this->garageText($this->garageElement($xpath, '//*[@data-stock-count]')));
    }

    public function test_whole_card_links_to_the_vehicle_without_a_repeated_primary_button(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.index')));
        $card = $this->garageElement($xpath, '//li[@data-slot="vehicle-card"][@data-stock-vehicle="'.$vehicle->id.'"]');

        $this->assertSame(route('garage.vehicles.show', $vehicle), $this->garageElement($xpath, './/h2/a[@data-slot="vehicle-card-link"]', $card)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('.//a[@data-slot="button"]', $card)->length, 'Sem "Ver detalhes" repetido em cada card.');
        $this->assertStringNotContainsString('Ver detalhes', $this->garageText($card));
    }

    public function test_search_matches_plate_model_chassis_and_previous_plate(): void
    {
        $civic = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23', 'chassis' => '9BWZZZ377VT004277']);
        $onix = $this->stockVehicle($this->garage, ['brand' => 'Chevrolet', 'model' => 'Onix', 'license_plate' => 'ONX2B34']);
        VehiclePlate::create(['vehicle_id' => $onix->id, 'plate' => 'OLD9Z99', 'started_at' => now()->subYears(3), 'ended_at' => now()->subYear(), 'source' => 'manual']);

        $this->assertStockShows(['busca' => 'civ-1c23'], [$civic], [$onix]);
        $this->assertStockShows(['busca' => 'onix'], [$onix], [$civic]);
        $this->assertStockShows(['busca' => 'honda civic'], [$civic], [$onix]);
        $this->assertStockShows(['busca' => '377VT0042'], [$civic], [$onix]);
        $this->assertStockShows(['busca' => 'OLD9Z99'], [$onix], [$civic]);
    }

    public function test_search_without_results_offers_to_clear_the_filters(): void
    {
        $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', ['busca' => 'Ferrari']))
            ->assertOk()
            ->assertSee('data-stock-empty="filtered"', false)
            ->assertSee('Nenhum veículo encontrado')
            ->assertSee('0 veículos de 1 no estoque')
            ->assertSee('Limpar filtros')
            ->assertDontSee('data-stock-empty="all"', false);
    }

    public function test_empty_stock_shows_the_call_to_action_without_toolbar(): void
    {
        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('data-stock-empty="all"', false)
            ->assertSee('Nenhum veículo no estoque')
            ->assertDontSee('data-slot="stock-toolbar"', false);
    }

    public function test_provenance_filters_split_the_stock_and_count_each_chip(): void
    {
        $sealed = $this->stockVehicle($this->garage, ['model' => 'Selado']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $sealed->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $sealed->id]);

        $declaredOnly = $this->stockVehicle($this->garage, ['model' => 'Declarado']);
        Maintenance::factory()->declaredByGarage()->create(['vehicle_id' => $declaredOnly->id, 'user_id' => $this->garage->id]);

        $withoutHistory = $this->stockVehicle($this->garage, ['model' => 'Vazio']);

        $pending = $this->consignedStockVehicle($this->garage, 'pending', ['model' => 'Pendente']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $pending->id]);

        $approved = $this->consignedStockVehicle($this->garage, 'approved', ['model' => 'Aprovado']);

        $this->assertStockShows(['filtro' => 'selo'], [$sealed], [$declaredOnly, $withoutHistory, $pending, $approved]);
        $this->assertStockShows(['filtro' => 'declaradas'], [$declaredOnly], [$sealed, $withoutHistory, $pending, $approved]);
        $this->assertStockShows(['filtro' => 'sem-historico'], [$withoutHistory, $approved], [$sealed, $declaredOnly, $pending]);
        $this->assertStockShows(['filtro' => 'consignacao'], [$pending, $approved], [$sealed, $declaredOnly, $withoutHistory]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertViewHas('counts', [
                '' => 5,
                'selo' => 1,
                'declaradas' => 1,
                'sem-historico' => 2,
                'consignacao' => 2,
            ]);
    }

    public function test_unknown_filter_and_order_fall_back_to_the_defaults(): void
    {
        $this->stockVehicle($this->garage);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', ['filtro' => 'qualquer', 'ordem' => 'drop_table']))
            ->assertOk()
            ->assertViewHas('stock', fn ($stock): bool => $stock->filter === '' && $stock->orderValue() === 'entrada_desc');
    }

    public function test_sort_orders_by_entry_vehicle_year_km_and_last_maintenance(): void
    {
        $older = $this->stockVehicle($this->garage, ['brand' => 'Volkswagen', 'model' => 'Gol', 'year' => 2015, 'current_kilometers' => 90_000], now()->subMonths(3)->toDateTimeString());
        $newer = $this->stockVehicle($this->garage, ['brand' => 'Fiat', 'model' => 'Argo', 'year' => 2022, 'current_kilometers' => 20_000], now()->subDay()->toDateTimeString());
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $older->id, 'maintenance_date' => now()->subDays(2)]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $newer->id, 'maintenance_date' => now()->subYear()]);

        $this->assertStockOrder([], [$newer, $older]);
        $this->assertStockOrder(['ordem' => 'entrada_asc'], [$older, $newer]);
        $this->assertStockOrder(['ordem' => 'veiculo_asc'], [$newer, $older]);
        $this->assertStockOrder(['ordem' => 'ano_desc'], [$newer, $older]);
        $this->assertStockOrder(['ordem' => 'km_asc'], [$newer, $older]);
        $this->assertStockOrder(['ordem' => 'km_desc'], [$older, $newer]);
        $this->assertStockOrder(['ordem' => 'manutencao_desc'], [$older, $newer]);
    }

    public function test_table_view_has_sortable_headers_and_is_remembered(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'maintenance_date' => '2025-03-10']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.index', ['visao' => 'tabela', 'ordem' => 'km_asc'])));

        $table = $this->garageElement($xpath, '//div[@data-slot="table"][@data-stack="md"]//table');
        $this->assertSame('ascending', $this->garageElement($xpath, './/th[@data-sort="km"]', $table)->getAttribute('aria-sort'));
        $this->assertStringContainsString('ordem=veiculo_asc', $this->garageElement($xpath, './/th[@data-sort="veiculo"]//a', $table)->getAttribute('href'));
        $row = $this->garageElement($xpath, './/tr[@data-stock-vehicle="'.$vehicle->id.'"]', $table);
        $this->assertSame(route('garage.vehicles.show', $vehicle), $this->garageElement($xpath, './/th[@scope="row"]//a', $row)->getAttribute('href'));
        $this->assertStringContainsString('CIV1C23', $this->garageText($row));
        $this->assertStringContainsString('1 com selo · 0 declaradas', $this->garageText($row));
        $this->assertStringContainsString('10/03/2025', $this->garageText($row));
        $this->garageElement($xpath, '//nav[@aria-label="Visualização"]//a[@aria-current="page"][contains(., "Tabela")]');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertViewHas('stockView', 'tabela')
            ->assertSee('data-stack="md"', false);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', ['visao' => 'cards']))
            ->assertOk()
            ->assertViewHas('stockView', 'cards')
            ->assertDontSee('data-stack="md"', false);
    }

    public function test_pending_consignment_in_table_opens_but_hides_the_previous_history(): void
    {
        $pending = $this->consignedStockVehicle($this->garage, 'pending', ['brand' => 'Fiat', 'model' => 'Toro']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $pending->id]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.index', ['visao' => 'tabela'])));
        $row = $this->garageElement($xpath, '//tr[@data-stock-vehicle="'.$pending->id.'"]');

        // A ficha abre: é nela que a loja registra as manutenções do veículo consignado.
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('garage.vehicles.show', $pending).'"]', $row)->length);
        $this->assertStringContainsString('Histórico aguardando liberação', $this->garageText($row));
        $this->assertStringContainsString('Só o que você registrou', $this->garageText($row));
        $this->assertStringNotContainsString('com selo', $this->garageText($row));
    }

    public function test_stock_is_paginated_and_keeps_the_query_string(): void
    {
        foreach (range(1, 25) as $index) {
            $this->stockVehicle($this->garage, ['brand' => 'Fiat', 'model' => 'Uno '.$index]);
        }

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', ['busca' => 'fiat']))
            ->assertOk()
            ->assertViewHas('vehicles', fn ($vehicles): bool => $vehicles->count() === 24 && $vehicles->total() === 25)
            ->assertSee('busca=fiat&amp;page=2', false);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', ['busca' => 'fiat', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('vehicles', fn ($vehicles): bool => $vehicles->count() === 1);
    }

    public function test_owned_vehicle_card_offers_add_cover_and_consignment_does_not(): void
    {
        $owned = $this->stockVehicle($this->garage, ['cover_photo_path' => null]);
        $approved = $this->consignedStockVehicle($this->garage, 'approved', ['cover_photo_path' => null]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('href="'.route('garage.vehicles.edit', $owned).'#capas"', false)
            ->assertDontSee('href="'.route('garage.vehicles.edit', $approved).'#capas"', false);
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<Vehicle>  $visible
     * @param  list<Vehicle>  $hidden
     */
    private function assertStockShows(array $query, array $visible, array $hidden): void
    {
        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', $query))
            ->assertOk()
            ->assertViewHas('vehicles', function ($vehicles) use ($visible, $hidden): bool {
                $ids = collect($vehicles->items())->map(fn (Vehicle $vehicle): int => $vehicle->id)->all();

                return collect($visible)->every(fn (Vehicle $vehicle): bool => in_array($vehicle->id, $ids, true))
                    && collect($hidden)->every(fn (Vehicle $vehicle): bool => ! in_array($vehicle->id, $ids, true));
            });
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<Vehicle>  $expected
     */
    private function assertStockOrder(array $query, array $expected): void
    {
        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index', $query))
            ->assertOk()
            ->assertViewHas('vehicles', fn ($vehicles): bool => collect($vehicles->items())->map(fn (Vehicle $vehicle): int => $vehicle->id)->all() === collect($expected)->map(fn (Vehicle $vehicle): int => $vehicle->id)->all());
    }
}
