<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Shell do admin: sidebar por domínio com ícone e aria-current, menu de conta no rodapé da sidebar,
 * topbar com trilha (sem H1) e busca de veículo, documento rolando e gaveta HSOverlay no celular.
 */
class AdminShellTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_sidebar_groups_destinations_by_domain_with_an_icon_on_each_item(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));
        $nav = $this->adminElement($xpath, '//aside[@id="nav-admin"]//nav[@aria-label="Administração"]');

        $groupLabels = array_map(
            fn ($label): string => $this->adminText($label),
            $this->adminElements($xpath, './/ul[@aria-labelledby]/preceding-sibling::p', $nav),
        );
        $this->assertSame(['Cadastros', 'Frota', 'Conteúdo', 'Catálogo'], $groupLabels);

        $links = $this->adminElements($xpath, './/a', $nav);
        $this->assertSame(
            ['Visão geral', 'Usuários', 'Oficinas', 'Prospecção', 'Veículos', 'Manutenções', 'Artigos do blog', 'Categorias do blog', 'Marcas e modelos'],
            array_map(fn ($link): string => $this->adminText($link), $links),
        );

        foreach ($links as $link) {
            $icon = $this->adminElement($xpath, './/*[local-name()="svg"][@data-slot="icon"]', $link);
            $this->assertSame('true', $icon->getAttribute('aria-hidden'));
            $this->assertStringContainsString('size-5', $icon->getAttribute('class'));
        }

        $hrefs = array_map(fn ($link): string => $link->getAttribute('href'), $links);
        $this->assertContains(route('admin.users.index'), $hrefs);
        $this->assertNotContains(route('admin.maps.workshops'), $hrefs, 'Mapa é uma visão da lista de oficinas, não um item do menu.');
        $this->assertNotContains(route('admin.maps.users'), $hrefs);
    }

    public function test_group_labels_use_a_readable_size_and_name_their_list(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        foreach ($this->adminElements($xpath, '//nav[@aria-label="Administração"]//ul[@aria-labelledby]') as $groupList) {
            $label = $this->adminElement($xpath, '//*[@id="'.$groupList->getAttribute('aria-labelledby').'"]');

            $this->assertStringContainsString('text-xs', $label->getAttribute('class'));
            $this->assertStringNotContainsString('0.65rem', $label->getAttribute('class'));
            $this->assertStringNotContainsString('text-automotive-500', $label->getAttribute('class'));
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function areasAndActiveItems(): array
    {
        return [
            'visão geral' => ['admin.dashboard', 'Visão geral'],
            'usuários' => ['admin.users.index', 'Usuários'],
            'veículos' => ['admin.vehicles.index', 'Veículos'],
            'manutenções' => ['admin.maintenances.index', 'Manutenções'],
            'oficinas' => ['admin.workshops.index', 'Oficinas'],
            'mapa de oficinas ativa Oficinas' => ['admin.maps.workshops', 'Oficinas'],
            'mapa de proprietários ativa Usuários' => ['admin.maps.users', 'Usuários'],
            'artigos do blog' => ['admin.blog.index', 'Artigos do blog'],
            'novo artigo' => ['admin.blog.create', 'Artigos do blog'],
            'categorias do blog' => ['admin.blog.categories.index', 'Categorias do blog'],
            'marcas e modelos' => ['admin.brands.index', 'Marcas e modelos'],
            'nova marca' => ['admin.brands.create', 'Marcas e modelos'],
        ];
    }

    #[DataProvider('areasAndActiveItems')]
    public function test_only_the_open_area_is_marked_as_current_page(string $routeName, string $activeLabel): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route($routeName)));

        $current = $this->adminElements($xpath, '//nav[@aria-label="Administração"]//a[@aria-current="page"]');

        $this->assertCount(1, $current, "Em {$routeName} deveria haver um só item atual no menu.");
        $this->assertSame($activeLabel, $this->adminText($current[0]));
    }

    public function test_user_detail_marks_users_as_current(): void
    {
        $account = User::factory()->asGarage()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $account)));

        $current = $this->adminElements($xpath, '//nav[@aria-label="Administração"]//a[@aria-current="page"]');
        $this->assertCount(1, $current);
        $this->assertSame('Usuários', $this->adminText($current[0]));
    }

    public function test_account_menu_lives_in_the_sidebar_footer(): void
    {
        $admin = $this->adminUser(['name' => 'Ana Admin', 'email' => 'ana@revisalog.test']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $xpath = $this->adminPage($response);

        $footer = $this->adminElement($xpath, '//aside[@id="nav-admin"]//*[@data-admin-account]');
        $trigger = $this->adminElement($xpath, './/button[@aria-haspopup="menu"]', $footer);
        $this->assertSame('Conta de Ana Admin', $trigger->getAttribute('aria-label'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));

        $menu = $this->adminElement($xpath, '//*[@id="'.$trigger->getAttribute('aria-controls').'"]');
        $this->assertSame('menu', $menu->getAttribute('role'));
        $this->assertStringContainsString('ana@revisalog.test', $this->adminText($menu));

        $items = [];
        foreach ($this->adminElements($xpath, './/*[@role="menuitem"]', $menu) as $item) {
            $items[$this->adminText($item)] = $item->getAttribute('href');
        }

        $this->assertSame(route('user.dashboard'), $items['Minha área de proprietário'] ?? null);
        $this->assertSame(route('home'), $items['Ver site'] ?? null);
        $this->assertSame(route('vehicle.search'), $items['Buscar veículo'] ?? null);
        $this->assertArrayHasKey('Sair', $items);

        if (Route::has('account.edit')) {
            $this->assertSame(route('account.edit'), $items['Minha conta'] ?? null);
        }

        $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('logout').'"'), 'O Sair aparece uma vez só, no menu de conta.');
        $this->assertStringNotContainsString('badge-orange', $response->getContent());
    }

    public function test_account_menu_offers_the_own_area_of_the_account_type(): void
    {
        $admin = User::factory()->asGarage()->asAdmin()->create();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.dashboard')));
        $link = $this->adminElement($xpath, '//*[@data-admin-account]//a[@role="menuitem"][@href="'.route('garage.dashboard').'"]');

        $this->assertSame('Minha área de lojista', $this->adminText($link));
    }

    public function test_topbar_shows_the_trail_and_no_heading(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.brands.create')));
        $topbar = $this->adminElement($xpath, '//header[@data-admin-topbar]');

        $this->assertSame(0, $xpath->query('.//h1|.//h2', $topbar)->length, 'A topbar não tem título: o H1 é do page-header.');
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame('Nova marca', $this->adminText($this->adminElement($xpath, '//main//h1')));

        $this->assertSame([
            ['label' => 'Catálogo', 'href' => null, 'current' => false],
            ['label' => 'Marcas e modelos', 'href' => route('admin.brands.index'), 'current' => false],
            ['label' => 'Nova marca', 'href' => null, 'current' => true],
        ], $this->adminTrail($xpath));
    }

    public function test_topbar_search_sends_a_get_to_the_vehicle_list(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $form = $this->adminElement($xpath, '//header[@data-admin-topbar]//form[@role="search"]');
        $this->assertSame('GET', $form->getAttribute('method'));
        $this->assertSame(route('admin.vehicles.index'), $form->getAttribute('action'));

        $input = $this->adminElement($xpath, './/input[@name="search"]', $form);
        $this->assertSame('search', $input->getAttribute('type'));
        $label = $this->adminElement($xpath, './/label[@for="'.$input->getAttribute('id').'"]', $form);
        $this->assertSame('Buscar veículo por placa, chassi ou RENAVAM', $this->adminText($label));

        $mobileSearch = $this->adminElement($xpath, '//header[@data-admin-topbar]//a[@aria-label="Buscar veículo"]');
        $this->assertSame(route('admin.vehicles.index'), $mobileSearch->getAttribute('href'));
    }

    public function test_vehicle_list_keeps_a_single_search_field(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.index', ['search' => 'ABC1D23'])));

        $this->assertSame(0, $xpath->query('//header[@data-admin-topbar]//form')->length);
        $this->assertSame(1, $xpath->query('//input[@name="search"]')->length);
        $this->assertSame('ABC1D23', $this->adminElement($xpath, '//input[@id="admin-vehicle-search"]')->getAttribute('value'));
    }

    public function test_document_scrolls_and_the_sidebar_is_sticky_from_md(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $sidebar = $this->adminElement($xpath, '//aside[@id="nav-admin"]');
        $sidebarClasses = explode(' ', $sidebar->getAttribute('class'));
        foreach (['hs-overlay', 'theme-inverse', 'md:sticky', 'md:top-0', 'h-dvh', 'md:flex', 'md:translate-x-0', 'motion-reduce:transition-none'] as $class) {
            $this->assertContains($class, $sidebarClasses, "Faltou {$class} na sidebar.");
        }

        $main = $this->adminElement($xpath, '//main[@id="conteudo"]');
        $this->assertStringNotContainsString('overflow-y-auto', $main->getAttribute('class'));
        $this->assertStringNotContainsString('h-screen', $this->adminElement($xpath, '//body')->getAttribute('class'));
        $this->assertSame(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " h-screen ")]')->length);
    }

    public function test_mobile_drawer_is_opened_by_a_real_button(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $open = $this->adminElement($xpath, '//header[@data-admin-topbar]//button[@data-hs-overlay="#nav-admin"]');
        $this->assertSame('Abrir menu', $open->getAttribute('aria-label'));
        $this->assertSame('nav-admin', $open->getAttribute('aria-controls'));
        $this->assertSame('false', $open->getAttribute('aria-expanded'));
        $this->assertSame('dialog', $open->getAttribute('aria-haspopup'));

        $close = $this->adminElement($xpath, '//aside[@id="nav-admin"]//button[@data-hs-overlay="#nav-admin"]');
        $this->assertSame('Fechar menu', $close->getAttribute('aria-label'));

        $sidebar = $this->adminElement($xpath, '//aside[@id="nav-admin"]');
        $this->assertSame('md', $sidebar->getAttribute('data-close-from'));
        $this->assertSame('md', $sidebar->getAttribute('data-shell-overlay-close-from'));
        $this->assertSame(0, $xpath->query('//input[@type="checkbox"][contains(@class, "peer")]')->length);
    }

    public function test_layout_renders_the_confirmation_dialog_and_toaster_once(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $this->assertSame(1, $xpath->query('//dialog[@data-ui-confirm-dialog]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-ui-toaster]')->length);
    }

    public function test_brand_name_is_written_revisalog_in_the_sidebar(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $logo = $this->adminElement($xpath, '//aside[@id="nav-admin"]//a[@href="'.route('admin.dashboard').'"][.//img]');
        $this->assertStringContainsString('RevisaLog', $this->adminText($logo));
    }

    public function test_workshop_account_link_in_workshop_list_points_to_the_user_detail(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        Workshop::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Oficina do Zé',
            'street' => 'Rua A',
            'number' => '',
            'neighborhood' => '',
            'city' => 'Recife',
            'state' => 'PE',
            'latitude' => null,
            'longitude' => null,
        ]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.workshops.index')));

        $row = $this->adminElement($xpath, '//table//tr[.//th[@scope="row"][contains(., "Oficina do Zé")]]');
        $this->assertSame(route('admin.users.show', $owner), $this->adminElement($xpath, './/th//a', $row)->getAttribute('href'));
        $this->assertStringContainsString('Recife/PE', $this->adminText($row));
        $this->assertStringContainsString('Rua A', $this->adminText($row));
        $this->assertStringNotContainsString('Rua A,', $this->adminText($row), 'Endereço sem número não deixa vírgula solta.');
        $this->assertStringContainsString('Sem coordenadas', $this->adminText($row));

        $switch = $this->adminElement($xpath, '//nav[@data-slot="view-switch"]');
        $this->assertSame('Lista', $this->adminText($this->adminElement($xpath, './/a[@aria-current="page"]', $switch)));
        $this->assertSame(route('admin.maps.workshops'), $this->adminElement($xpath, './/a[not(@aria-current)]', $switch)->getAttribute('href'));
    }
}
