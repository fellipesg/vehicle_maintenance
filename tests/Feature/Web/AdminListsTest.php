<?php

namespace Tests\Feature\Web;

use App\Models\BlogPost;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Listas e detalhes do admin com os componentes x-ui: totais reais, tabelas com legenda e estado
 * vazio, filtros com estado que não depende só de cor, mapas com lista acessível.
 */
class AdminListsTest extends TestCase
{
    use InspectsAdminPages;
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_overview_shows_real_totals_as_linked_stats(): void
    {
        $admin = $this->adminUser();
        User::factory()->asUser()->count(2)->create();
        User::factory()->asGarage()->create();
        Vehicle::factory()->count(3)->create();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.dashboard')));

        $stats = [];
        foreach ($this->adminElements($xpath, '//*[@data-slot="stat"]') as $stat) {
            $label = $this->adminText($this->adminElement($xpath, './/dt', $stat));
            $stats[$label] = [
                'value' => $this->adminText($this->adminElement($xpath, './/dd', $stat)),
                'href' => $stat->getAttribute('href'),
            ];
        }

        $this->assertSame('4', $stats['Usuários']['value']);
        $this->assertSame(route('admin.users.index'), $stats['Usuários']['href']);
        $this->assertSame('3', $stats['Veículos']['value']);
        $this->assertSame(route('admin.vehicles.index'), $stats['Veículos']['href']);
        $this->assertSame(route('admin.maintenances.index'), $stats['Manutenções']['href']);
        $this->assertSame(route('admin.workshops.index'), $stats['Oficinas']['href']);

        $recent = $this->adminElement($xpath, '//section[@id="cadastros-recentes"]');
        $this->assertSame('cadastros-recentes-titulo', $recent->getAttribute('aria-labelledby'));
        $this->assertSame(4, $xpath->query('.//ul[@data-slot="recent-users"]/li', $recent)->length);
        $this->assertSame(route('admin.users.index'), $this->adminElement($xpath, './/a[contains(., "Ver todos os usuários")]', $recent)->getAttribute('href'));
    }

    public function test_user_type_uses_the_glossary_terms(): void
    {
        $owner = User::factory()->asUser()->create();
        $garage = User::factory()->asGarage()->create();
        $workshop = User::factory()->asWorkshop()->create();

        foreach ([[$owner, 'Proprietário', 'info'], [$garage, 'Lojista', 'neutral'], [$workshop, 'Oficina', 'primary']] as [$account, $label, $variant]) {
            $badge = $this->uiElement($this->renderUi('<x-admin.user-type-badge :user="$account" />', ['account' => $account]), '//*[@data-slot="badge"]');

            $this->assertSame($label, $this->uiText($badge));
            $this->assertSame($variant, $badge->getAttribute('data-variant'));
        }

        $this->assertUiRejects('<x-admin.user-type-badge />', 'x-admin.user-type-badge precisa de user');
    }

    public function test_view_switch_marks_the_open_view(): void
    {
        $xpath = $this->renderUi('<x-admin.view-switch list="/lista" map="/mapa" current="map" label="Visualização das oficinas" />');

        $switch = $this->uiElement($xpath, '//nav[@data-slot="view-switch"]');
        $this->assertSame('Visualização das oficinas', $switch->getAttribute('aria-label'));

        $current = $this->uiElement($xpath, '//a[@aria-current="page"]');
        $this->assertSame('/mapa', $current->getAttribute('href'));
        $this->assertSame('Mapa', $this->uiText($current));
        $this->assertHasClasses(['font-semibold', 'bg-accent'], $current);
        $this->assertSame(1, $this->uiCount($xpath, '//a[@aria-current]'));

        $this->assertUiRejects('<x-admin.view-switch list="/lista" map="/mapa" current="tabela" />', 'x-admin.view-switch: current "tabela" não existe');
    }

    public function test_vehicle_search_shows_the_term_and_a_way_back(): void
    {
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.index', ['search' => 'ZZZ9Z99'])));

        $summary = $this->adminElement($xpath, '//*[@data-admin-vehicle-search-summary]');
        $this->assertStringContainsString('0 resultados para “ZZZ9Z99”', $this->adminText($summary));
        $this->assertSame(route('admin.vehicles.index'), $this->adminElement($xpath, './/a', $summary)->getAttribute('href'));

        $empty = $this->adminElement($xpath, '//tr[@data-slot="table-empty"]');
        $this->assertStringContainsString('Nenhum veículo encontrado', $this->adminText($empty));
        $this->assertSame(route('admin.vehicles.index'), $this->adminElement($xpath, './/a[contains(., "Limpar busca")]', $empty)->getAttribute('href'));
    }

    public function test_vehicle_filter_marks_the_current_provenance_without_relying_on_color(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(6)->for($vehicle)->for($owner)->declaredByOwner()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.show', [$vehicle, 'verified' => '1'])));

        // A ficha do admin é o mesmo <x-vehicle.detail> dos portais: filtro em botões aria-pressed
        // dentro de um form GET, que funciona sem JS.
        $filter = $this->adminElement($xpath, '//*[@id="historico"]//*[@role="group"][@aria-label="Filtrar o histórico por procedência"]');
        $buttons = $this->adminElements($xpath, './button', $filter);
        $this->assertSame(['Todas 6', 'Selo da oficina 0', 'Declaradas 6'], array_map(fn ($button): string => $this->adminText($button), $buttons));
        $this->assertSame(['false', 'true', 'false'], array_map(fn ($button): string => $button->getAttribute('aria-pressed'), $buttons));
        $this->assertSame('verified', $buttons[1]->getAttribute('name'));
        $this->assertSame('GET', strtoupper($this->adminElement($xpath, './ancestor::form', $filter)->getAttribute('method')));

        $empty = $this->adminElement($xpath, '//*[@id="historico"]//*[@data-provenance-empty]');
        $this->assertFalse($empty->hasAttribute('hidden'));
        $this->assertStringContainsString('Nenhuma manutenção com Selo da oficina', $this->adminText($empty));
        $this->assertSame(route('admin.vehicles.show', $vehicle).'#historico', $this->adminElement($xpath, './/a[@data-provenance-clear]', $empty)->getAttribute('href'));
    }

    public function test_maintenance_filters_are_toggle_buttons_styled_by_aria_pressed(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index', ['verified' => '0'])));

        $buttons = $this->adminElements($xpath, '//*[@role="group"][@aria-label="Filtrar por procedência"]/button');
        $this->assertCount(3, $buttons);

        foreach ($buttons as $button) {
            $this->assertStringContainsString('aria-pressed:bg-accent', $button->getAttribute('class'));
            $this->assertStringNotContainsString('!bg-wrench-100', $button->getAttribute('class'));
            // Submit do formulário de filtros: sem JS, recarrega com ?verified=; com JS, troca no lugar.
            $this->assertSame('submit', $button->getAttribute('type'));
            $this->assertSame('verified', $button->getAttribute('name'));
            $this->assertSame('admin-maintenances-results', $button->getAttribute('aria-controls'));
        }

        $this->assertSame('true', $buttons[2]->getAttribute('aria-pressed'));
        $this->assertSame('Nenhuma manutenção declarada', $this->adminText($this->adminElement($xpath, '//tr[@data-slot="table-empty"]//p')));

        $template = $this->adminElement($xpath, '//template[@data-admin-maintenances-error-template]');
        $templateHtml = $template->ownerDocument->saveHTML($template);
        $this->assertStringContainsString('role="alert"', $templateHtml);
        $this->assertStringContainsString('data-admin-maintenances-retry', $templateHtml);
    }

    public function test_maintenance_filter_script_keeps_the_state_in_aria_pressed_only(): void
    {
        $script = file_get_contents(resource_path('js/admin-maintenances-filters.js'));

        $this->assertStringNotContainsString('!bg-wrench-100', $script);
        $this->assertStringContainsString("button.setAttribute('aria-pressed'", $script);
        $this->assertStringContainsString('push: false', $script);
    }

    public function test_blog_list_uses_article_terms_and_status_badges(): void
    {
        BlogPost::factory()->create(['title' => 'Publicado já']);
        BlogPost::factory()->scheduled()->create(['title' => 'Para amanhã']);
        BlogPost::factory()->draft()->create(['title' => 'Ainda escrevendo']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.blog.index')));

        $this->assertSame(route('admin.blog.create'), $this->adminElement($xpath, '//header[@data-slot="page-header"]//a[contains(., "Novo artigo")]')->getAttribute('href'));

        $statuses = [];
        foreach ($this->adminElements($xpath, '//tbody/tr') as $row) {
            $statuses[$this->adminText($this->adminElement($xpath, './/th//a', $row))] = $this->adminText($this->adminElement($xpath, './/*[@data-slot="badge"]', $row));
        }

        $this->assertSame('Publicado', $statuses['Publicado já']);
        $this->assertSame('Agendado', $statuses['Para amanhã']);
        $this->assertSame('Rascunho', $statuses['Ainda escrevendo']);

        $filter = $this->adminElement($xpath, '//nav[@aria-label="Filtrar artigos por situação"]');
        $this->assertSame('Todos 3', $this->adminText($this->adminElement($xpath, './/a[@aria-current="page"]', $filter)));
        $this->assertSame(
            ['Todos 3', 'Publicados 1', 'Agendados 1', 'Rascunhos 1'],
            array_map(fn ($link): string => $this->adminText($link), $this->adminElements($xpath, './/a', $filter)),
        );
    }

    public function test_brand_list_links_to_models_and_edit_with_context_for_screen_readers(): void
    {
        $brand = VehicleBrand::factory()->create(['name' => 'Honda', 'is_active' => false]);
        VehicleModel::factory()->create(['vehicle_brand_id' => $brand->id]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.index')));
        $row = $this->adminElement($xpath, '//tbody/tr[.//th[contains(., "Honda")]]');

        $this->assertSame(route('admin.brands.show', $brand), $this->adminElement($xpath, './/th//a', $row)->getAttribute('href'));
        $this->assertSame('Inativa', $this->adminText($this->adminElement($xpath, './/*[@data-slot="badge"]', $row)));
        $menu = $this->adminElement($xpath, './/*[@data-slot="row-actions"]', $row);
        $this->assertSame('Ações para a marca Honda', $this->adminElement($xpath, './/button[@aria-haspopup="menu"]', $menu)->getAttribute('aria-label'));
        $edit = $this->adminElement($xpath, './/a[@role="menuitem"][@href="'.route('admin.brands.edit', $brand).'"]', $menu);
        $this->assertSame('Editar marca', $this->adminText($edit));
        $this->assertSame(route('admin.brands.show', $brand), $this->adminElement($xpath, './/a[@role="menuitem"][contains(., "Ver modelos")]', $menu)->getAttribute('href'));
    }

    public function test_maps_list_the_pins_as_text_and_escape_them(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->asUser()->create([
            'name' => '<b>Dono</b>',
            'city' => 'Olinda',
            'latitude' => -8.0,
            'longitude' => -34.8,
        ]);
        Workshop::factory()->create(['name' => 'Oficina Norte', 'latitude' => -8.05, 'longitude' => -34.9]);

        $usersMap = $this->actingAs($admin)->get(route('admin.maps.users'));
        $usersMap->assertDontSee('<b>Dono</b>', false);
        $xpath = $this->adminPage($usersMap);

        $list = $this->adminElement($xpath, '//section[@aria-labelledby="admin-map-lista-titulo"]');
        $link = $this->adminElement($xpath, './/a', $list);
        $this->assertSame('<b>Dono</b>', $this->adminText($link));
        $this->assertSame(route('admin.users.show', $owner), $link->getAttribute('href'));
        $this->assertSame('Mapa dos proprietários', $this->adminElement($xpath, '//*[@id="admin-map"]')->getAttribute('aria-label'));
        $this->assertStringContainsString('isolate', $this->adminElement($xpath, '//*[@id="admin-map"]')->getAttribute('class'));
        $this->assertSame('owner', $this->adminElement($xpath, '//*[@id="admin-map"]')->getAttribute('data-admin-map-tone'));

        $workshopsMap = $this->adminPage($this->actingAs($admin)->get(route('admin.maps.workshops')));
        $this->assertStringContainsString('Oficina Norte', $this->adminText($this->adminElement($workshopsMap, '//section[@aria-labelledby="admin-map-lista-titulo"]')));
        $switch = $this->adminElement($workshopsMap, '//nav[@data-slot="view-switch"]');
        $this->assertSame('Mapa', $this->adminText($this->adminElement($workshopsMap, './/a[@aria-current="page"]', $switch)));
    }
}
