<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Veículos, ficha do veículo, oficinas e mapas do admin: tabelas empilhadas com ordenação e menu ⋯,
 * a mesma ficha dos portais (x-vehicle.detail), filtro de localização com contagem e mapas com a
 * lista acessível e o atalho para as pendências.
 */
class AdminFleetAndWorkshopListsTest extends TestCase
{
    use InspectsAdminPages;
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_vehicle_list_sorts_by_maintenances_and_offers_row_actions(): void
    {
        $owner = User::factory()->asUser()->create(['name' => 'Dona Clara']);
        $busy = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Toro', 'license_plate' => 'TOR0A11', 'created_at' => now()->subYear()]);
        $quiet = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Fit', 'created_at' => now()]);
        $owner->vehicles()->attach($busy->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $owner->tenant_id]);
        Maintenance::factory()->count(2)->for($busy)->create();

        $admin = $this->adminUser();

        $newest = $this->adminPage($this->actingAs($admin)->get(route('admin.vehicles.index')));
        $this->assertSame(['Honda Fit', 'Fiat Toro'], $this->vehicleNames($newest), 'Padrão: cadastro mais recente primeiro.');
        $this->assertSame('descending', $this->adminElement($newest, '//thead//th[.//a[contains(., "Cadastro")]]')->getAttribute('aria-sort'));
        $this->assertSame('md', $this->adminElement($newest, '//*[@data-slot="table"]')->getAttribute('data-stack'));

        $byCount = $this->adminPage($this->actingAs($admin)->get(route('admin.vehicles.index', ['ordenar' => 'manutencoes', 'direcao' => 'desc'])));
        $this->assertSame(['Fiat Toro', 'Honda Fit'], $this->vehicleNames($byCount));

        $row = $this->adminElement($byCount, '//tbody/tr[1]');
        $this->assertSame('Ações para o veículo Fiat Toro TOR0A11', $this->adminElement($byCount, './/*[@data-slot="row-actions"]//button[@aria-haspopup="menu"]', $row)->getAttribute('aria-label'));
        $items = [];
        foreach ($this->adminElements($byCount, './/*[@role="menuitem"]', $row) as $item) {
            $items[$this->adminText($item)] = $item->getAttribute('href');
        }
        $this->assertSame([
            'Abrir veículo' => route('admin.vehicles.show', $busy),
            'Ver manutenções' => route('admin.maintenances.index', ['veiculo' => $busy->id]),
            'Abrir proprietário atual' => route('admin.users.show', $owner),
        ], $items);

        $quietRow = $this->adminElement($byCount, '//tbody/tr[2]');
        $this->assertStringContainsString('Sem proprietário atual', $this->adminText($quietRow));
    }

    public function test_vehicle_detail_is_the_shared_vehicle_page_with_the_current_owner(): void
    {
        $owner = User::factory()->asGarage()->create(['name' => 'Loja Azul']);
        $vehicle = Vehicle::factory()->create(['brand' => 'VW', 'model' => 'Polo', 'renavam' => '12345678901']);
        $owner->vehicles()->attach($vehicle->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $owner->tenant_id]);
        Maintenance::factory()->for($vehicle)->sealedByWorkshop()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.show', $vehicle)));

        $detail = $this->adminElement($xpath, '//*[@data-vehicle-detail]');
        $this->assertSame('admin', $detail->getAttribute('data-portal'));
        $this->assertSame('VW Polo', $this->adminText($this->adminElement($xpath, '//h1')));

        $ownership = $this->adminElement($xpath, '//*[@data-slot="admin-vehicle-ownership"]');
        $this->assertSame(route('admin.users.show', $owner), $this->adminElement($xpath, './/a', $ownership)->getAttribute('href'));
        $this->assertStringContainsString('Lojista', $this->adminText($ownership));

        $this->assertStringContainsString('12345678901', $this->adminText($this->adminElement($xpath, '//*[@id="documentos"]')), 'O admin vê o RENAVAM completo.');
        $this->assertSame(route('admin.maintenances.index', ['veiculo' => $vehicle->id]), $this->adminElement($xpath, '//header[@data-slot="page-header"]//a[contains(., "Ver na lista de manutenções")]')->getAttribute('href'));
        $this->assertSame(1, $xpath->query('//*[@id="historico-lista"]//*[@data-maintenance-card][@data-verified="1"]')->length);
    }

    public function test_workshop_list_filters_by_location_with_counts_and_counts_seals(): void
    {
        $mapped = Workshop::factory()->create(['name' => 'Oficina Mapa', 'city' => 'Recife', 'latitude' => -8.0, 'longitude' => -34.9]);
        Workshop::factory()->create(['name' => 'Oficina Perdida', 'city' => 'Olinda', 'latitude' => null, 'longitude' => null]);
        Maintenance::factory()->count(3)->sealedByWorkshop()->create(['workshop_id' => $mapped->id]);

        $admin = $this->adminUser();
        $missing = Workshop::query()->whereNull('latitude')->count();
        $total = Workshop::count();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.workshops.index', ['localizacao' => 'sem-coordenadas'])));
        $filter = $this->adminElement($xpath, '//nav[@aria-label="Filtrar oficinas por localização"]');
        $this->assertSame(
            ['Todas '.$total, 'No mapa 1', 'Sem coordenadas '.$missing],
            array_map(fn ($link): string => $this->adminText($link), $this->adminElements($xpath, './a', $filter)),
        );
        $names = array_map(fn ($cell): string => $this->adminText($this->adminElement($xpath, './/a', $cell)), $this->adminElements($xpath, '//tbody/tr/th[@scope="row"]'));
        $this->assertContains('Oficina Perdida', $names);
        $this->assertNotContains('Oficina Mapa', $names);

        $bySeals = $this->adminPage($this->actingAs($admin)->get(route('admin.workshops.index', ['ordenar' => 'selos', 'direcao' => 'desc'])));
        $first = $this->adminElement($bySeals, '//tbody/tr[1]');
        $this->assertStringContainsString('Oficina Mapa', $this->adminText($first));
        $this->assertSame('3', $this->adminText($this->adminElement($bySeals, './td[@data-label="Manutenções com selo"]', $first)));
        $this->assertStringContainsString('Recife', $this->adminText($this->adminElement($bySeals, './th[@scope="row"]', $first)));

        $items = [];
        foreach ($this->adminElements($bySeals, './/*[@role="menuitem"]', $first) as $item) {
            $items[$this->adminText($item)] = $item->getAttribute('href');
        }
        $this->assertSame(route('admin.users.show', $mapped->user_id), $items['Abrir cadastro']);
        $this->assertSame(route('admin.maintenances.index', ['oficina' => $mapped->id]), $items['Ver manutenções']);
        $this->assertSame(route('admin.maps.workshops'), $items['Ver no mapa']);
    }

    public function test_workshop_search_matches_name_or_city_and_keeps_the_location_filter(): void
    {
        Workshop::factory()->create(['name' => 'Auto Center Norte', 'city' => 'Natal']);
        Workshop::factory()->create(['name' => 'Mecânica Sul', 'city' => 'Porto Alegre']);

        $admin = $this->adminUser();

        $byCity = $this->adminPage($this->actingAs($admin)->get(route('admin.workshops.index', ['q' => 'porto'])));
        $this->assertStringContainsString('Mecânica Sul', $this->adminText($this->adminElement($byCity, '//tbody')));
        $this->assertStringNotContainsString('Auto Center Norte', $this->adminText($this->adminElement($byCity, '//tbody')));

        $empty = $this->adminPage($this->actingAs($admin)->get(route('admin.workshops.index', ['q' => 'zzz', 'localizacao' => 'no-mapa'])));
        $this->assertStringContainsString('Nenhuma oficina para “zzz”', $this->adminText($this->adminElement($empty, '//tr[@data-slot="table-empty"]')));
        $this->assertSame('no-mapa', $this->adminElement($empty, '//form[@aria-label="Buscar oficinas"]//input[@type="hidden"][@name="localizacao"]')->getAttribute('value'));
    }

    public function test_maps_link_to_the_records_and_to_the_missing_coordinates_list(): void
    {
        $admin = $this->adminUser();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Pin', 'latitude' => -8.05, 'longitude' => -34.9]);
        Workshop::factory()->create(['latitude' => null, 'longitude' => null]);

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.maps.workshops')));

        $missing = $this->adminElement($xpath, '//a[@data-admin-map-missing]');
        $this->assertSame(route('admin.workshops.index', ['localizacao' => 'sem-coordenadas']), $missing->getAttribute('href'));
        $this->assertStringContainsString('sem coordenadas', $this->adminText($missing));

        $item = $this->adminElement($xpath, '//li[@data-admin-map-item="'.$workshop->id.'"]');
        $this->assertSame(route('admin.users.show', $workshop->user_id), $this->adminElement($xpath, './/a[@data-admin-map-pin-link="'.$workshop->id.'"]', $item)->getAttribute('href'));
        $focus = $this->adminElement($xpath, './/button[@data-admin-map-focus="'.$workshop->id.'"]', $item);
        $this->assertTrue($focus->hasAttribute('hidden'), 'O atalho para o mapa só aparece quando o mapa carrega.');
        $this->assertSame('Mostrar Oficina Pin no mapa', $focus->getAttribute('aria-label'));

        $this->assertSame('workshop', $this->adminElement($xpath, '//*[@id="admin-map"]')->getAttribute('data-admin-map-tone'));
        $status = $this->adminElement($xpath, '//*[@data-admin-map-status]');
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertStringContainsString('Carregando o mapa', $this->adminText($status));
        $this->assertStringNotContainsString('100vh', $this->actingAs($admin)->get(route('admin.maps.workshops'))->getContent());
    }

    public function test_owner_map_links_its_missing_count_to_the_filtered_user_list(): void
    {
        $admin = $this->adminUser(['latitude' => null, 'longitude' => null]);
        User::factory()->asUser()->create(['latitude' => null, 'longitude' => null]);

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.maps.users')));

        $missing = $this->adminElement($xpath, '//a[@data-admin-map-missing]');
        $this->assertSame(route('admin.users.index', ['perfil' => 'proprietarios', 'localizacao' => 'sem-coordenadas']), $missing->getAttribute('href'));
        $this->assertStringContainsString('os 2 proprietários sem coordenadas', $this->adminText($missing));
    }

    public function test_row_actions_component_needs_a_label(): void
    {
        $xpath = $this->renderUi('<x-admin.row-actions label="Ações para a marca Fiat" id="marca-1-acoes"><x-ui.dropdown-item href="/marcas/1">Ver modelos</x-ui.dropdown-item></x-admin.row-actions>');

        $trigger = $this->uiElement($xpath, '//*[@data-slot="row-actions"]//button[@aria-haspopup="menu"]');
        $this->assertSame('Ações para a marca Fiat', $trigger->getAttribute('aria-label'));
        $this->assertSame('marca-1-acoes-gatilho', $trigger->getAttribute('id'));
        $this->assertHasClasses(['size-10'], $trigger);
        $this->assertSame(1, $this->uiCount($xpath, '//*[@role="menu"]//a[@role="menuitem"]'));

        $this->assertUiRejects('<x-admin.row-actions><x-ui.dropdown-item href="/">Ver</x-ui.dropdown-item></x-admin.row-actions>', 'x-admin.row-actions precisa de label');
    }

    /**
     * @return list<string>
     */
    private function vehicleNames(\DOMXPath $xpath): array
    {
        return array_map(fn ($link): string => $this->adminText($link), $this->adminElements($xpath, '//tbody/tr/th[@scope="row"]//a'));
    }
}
