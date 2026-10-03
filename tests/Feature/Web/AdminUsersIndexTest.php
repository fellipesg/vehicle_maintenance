<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Lista de usuários do admin (/admin/usuarios): busca por nome ou e-mail, abas por perfil com
 * contagem, colunas ordenáveis, ações no menu ⋯, paginação e estados vazios que dizem o filtro.
 */
class AdminUsersIndexTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_sidebar_users_item_opens_the_list(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.index')));

        $current = $this->adminElement($xpath, '//nav[@aria-label="Administração"]//a[@aria-current="page"]');
        $this->assertSame('Usuários', $this->adminText($current));
        $this->assertSame(route('admin.users.index'), $current->getAttribute('href'));

        $this->assertSame('Usuários', $this->adminText($this->adminElement($xpath, '//h1')));
        $this->assertSame(['Cadastros', 'Usuários'], array_column($this->adminTrail($xpath), 'label'));

        $switch = $this->adminElement($xpath, '//nav[@data-slot="view-switch"]');
        $this->assertSame('Lista', $this->adminText($this->adminElement($xpath, './/a[@aria-current="page"]', $switch)));
        $this->assertSame(route('admin.maps.users'), $this->adminElement($xpath, './/a[not(@aria-current)]', $switch)->getAttribute('href'));
    }

    public function test_profile_tabs_filter_and_show_counts(): void
    {
        $admin = $this->adminUser(['name' => 'Ana Admin']);
        User::factory()->asUser()->create(['name' => 'Bruno Dono']);
        User::factory()->asGarage()->create(['name' => 'Loja Central']);
        User::factory()->asWorkshop()->create(['name' => 'Oficina Sul']);

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['perfil' => 'lojistas'])));

        $tabs = $this->adminElement($xpath, '//nav[@aria-label="Filtrar usuários por perfil"]');
        $counts = [];
        foreach ($this->adminElements($xpath, './a', $tabs) as $tab) {
            $count = $this->adminElement($xpath, './/*[@data-slot="segmented-count"]', $tab);
            $counts[trim(str_replace($this->adminText($count), '', $this->adminText($tab)))] = $this->adminText($count);
        }
        $this->assertSame(['Todos' => '4', 'Proprietários' => '2', 'Lojistas' => '1', 'Oficinas' => '1', 'Administradores' => '1'], $counts);
        $this->assertStringContainsString('Lojistas', $this->adminText($this->adminElement($xpath, './a[@aria-current="page"]', $tabs)));

        $this->assertSame(['Loja Central'], $this->rowNames($xpath));
        $this->assertSame('1 lojista', $this->adminText($this->adminElement($xpath, '//*[@data-admin-users-summary]')));

        $this->assertSame(['Ana Admin'], $this->rowNames($this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['perfil' => 'administradores'])))));
    }

    public function test_search_matches_name_or_email_and_keeps_the_tab(): void
    {
        $admin = $this->adminUser(['name' => 'Ana Admin']);
        User::factory()->asUser()->create(['name' => 'Carla Souza', 'email' => 'carla@example.test']);
        User::factory()->asUser()->create(['name' => 'Diego Lima', 'email' => 'diego.souza@example.test']);
        User::factory()->asGarage()->create(['name' => 'Souza Veículos']);

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['q' => 'SOUZA', 'perfil' => 'proprietarios'])));

        $this->assertEqualsCanonicalizing(['Carla Souza', 'Diego Lima'], $this->rowNames($xpath));
        $this->assertStringContainsString('2 resultados para “SOUZA”', $this->adminText($this->adminElement($xpath, '//*[@data-admin-users-summary]')));

        $form = $this->adminElement($xpath, '//form[@role="search"][@aria-label="Buscar usuários"]');
        $this->assertSame('GET', $form->getAttribute('method'));
        $this->assertSame('proprietarios', $this->adminElement($xpath, './/input[@type="hidden"][@name="perfil"]', $form)->getAttribute('value'));
        $this->assertSame('SOUZA', $this->adminElement($xpath, './/input[@name="q"]', $form)->getAttribute('value'));
        $this->assertSame('Buscar por nome ou e-mail', $this->adminText($this->adminElement($xpath, '//label[@for="admin-user-search"]')));

        $allTab = $this->adminElement($xpath, '//nav[@aria-label="Filtrar usuários por perfil"]/a[1]');
        $this->assertSame(route('admin.users.index', ['q' => 'SOUZA']), $allTab->getAttribute('href'));
        $this->assertStringContainsString('3', $this->adminText($allTab), 'A contagem das abas respeita a busca.');
    }

    public function test_empty_search_names_the_term_and_offers_a_way_back(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.index', ['q' => 'ninguém'])));

        $empty = $this->adminElement($xpath, '//tr[@data-slot="table-empty"]');
        $this->assertStringContainsString('Nenhum usuário para “ninguém”', $this->adminText($empty));
        $this->assertSame(route('admin.users.index'), $this->adminElement($xpath, './/a[contains(., "Limpar busca")]', $empty)->getAttribute('href'));
    }

    public function test_columns_sort_by_counts_and_signup_date(): void
    {
        $admin = $this->adminUser(['name' => 'Zeca Admin', 'created_at' => now()->subYears(3)]);
        $withVehicles = User::factory()->asUser()->create(['name' => 'Beto', 'created_at' => now()->subYear()]);
        User::factory()->asUser()->create(['name' => 'Alice', 'created_at' => now()->subDay()]);
        foreach (Vehicle::factory()->count(2)->create() as $vehicle) {
            $withVehicles->vehicles()->attach($vehicle->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $withVehicles->tenant_id]);
        }
        Maintenance::factory()->count(3)->for($withVehicles)->create();

        $byName = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index')));
        $this->assertSame(['Alice', 'Beto', 'Zeca Admin'], $this->rowNames($byName));
        $nameHeader = $this->adminElement($byName, '//thead//th[.//a[contains(., "Nome")]]');
        $this->assertSame('ascending', $nameHeader->getAttribute('aria-sort'));

        $vehiclesHeaderLink = $this->adminElement($byName, '//thead//th//a[contains(., "Veículos atuais")]');
        $this->assertStringContainsString('ordenar=veiculos', $vehiclesHeaderLink->getAttribute('href'));
        $this->assertStringContainsString('direcao=desc', $vehiclesHeaderLink->getAttribute('href'));

        $byVehicles = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['ordenar' => 'veiculos', 'direcao' => 'desc'])));
        $this->assertSame('Beto', $this->rowNames($byVehicles)[0]);
        $row = $this->adminElement($byVehicles, '//tbody/tr[1]');
        $this->assertSame('2', $this->adminText($this->adminElement($byVehicles, './td[@data-label="Veículos atuais"]', $row)));
        $this->assertSame('3', $this->adminText($this->adminElement($byVehicles, './td[@data-label="Manutenções lançadas"]', $row)));

        $bySignup = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['ordenar' => 'cadastro', 'direcao' => 'desc'])));
        $this->assertSame(['Alice', 'Beto', 'Zeca Admin'], $this->rowNames($bySignup));

        $unknown = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['ordenar' => 'senha', 'direcao' => 'lado'])));
        $this->assertSame(['Alice', 'Beto', 'Zeca Admin'], $this->rowNames($unknown), 'Ordenação desconhecida cai no nome.');
    }

    public function test_row_actions_live_in_a_named_menu(): void
    {
        $admin = $this->adminUser();
        $owner = User::factory()->asUser()->create(['name' => 'Rita']);
        Maintenance::factory()->for($owner)->create();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Rita'])));
        $row = $this->adminElement($xpath, '//tbody/tr[.//th[contains(., "Rita")]]');

        $trigger = $this->adminElement($xpath, './/*[@data-slot="row-actions"]//button[@aria-haspopup="menu"]', $row);
        $this->assertSame('Ações para Rita', $trigger->getAttribute('aria-label'));

        $items = [];
        foreach ($this->adminElements($xpath, './/*[@role="menuitem"]', $row) as $item) {
            $items[$this->adminText($item)] = $item->getAttribute('href');
        }
        $this->assertSame(route('admin.users.show', $owner), $items['Abrir cadastro']);
        $this->assertSame(route('admin.maintenances.index', ['usuario' => $owner->id]), $items['Ver manutenções lançadas']);
        $this->assertSame('mailto:'.$owner->email, $items['Enviar e-mail']);
    }

    public function test_list_is_paginated_by_25(): void
    {
        $admin = $this->adminUser(['name' => 'Aaa Admin']);
        User::factory()->asUser()->count(25)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index', ['page' => 2]));
        $xpath = $this->adminPage($response);

        $this->assertCount(1, $this->rowNames($xpath));
        $this->assertStringContainsString('26 usuários', $this->adminText($this->adminElement($xpath, '//*[@data-admin-users-summary]')));
    }

    public function test_accounts_without_coordinates_filter_comes_from_the_map(): void
    {
        $admin = $this->adminUser(['latitude' => -8.0, 'longitude' => -34.9]);
        User::factory()->asUser()->create(['name' => 'Sem mapa', 'latitude' => null, 'longitude' => null]);
        User::factory()->asUser()->create(['name' => 'No mapa', 'latitude' => -8.1, 'longitude' => -34.8]);

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.users.index', ['perfil' => 'proprietarios', 'localizacao' => 'sem-coordenadas'])));

        $this->assertSame(['Sem mapa'], $this->rowNames($xpath));
        $summary = $this->adminElement($xpath, '//*[@data-admin-users-summary]');
        $this->assertStringContainsString('sem coordenadas', $this->adminText($summary));
        $this->assertSame(route('admin.users.index', ['perfil' => 'proprietarios']), $this->adminElement($xpath, './/a[contains(., "Mostrar todas as localizações")]', $summary)->getAttribute('href'));
    }

    public function test_guests_and_non_admins_cannot_open_the_list(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login.admin'));

        $this->actingAs(User::factory()->asGarage()->create())
            ->get(route('admin.users.index'))
            ->assertRedirect(route('garage.dashboard'));
    }

    /**
     * @return list<string>
     */
    private function rowNames(\DOMXPath $xpath): array
    {
        return array_map(
            fn ($link): string => $this->adminText($link),
            $this->adminElements($xpath, '//tbody/tr/th[@scope="row"]//a'),
        );
    }
}
