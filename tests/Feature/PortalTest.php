<?php

namespace Tests\Feature;

use App\Enums\Portal;
use App\Models\User;
use App\Support\PortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * App\Enums\Portal é a fonte única de rótulo, Início, menu e ação principal de cada área.
 */
class PortalTest extends TestCase
{
    public function test_user_type_maps_to_portal_and_unknown_types_fall_back_to_owner(): void
    {
        $this->assertSame(Portal::Owner, Portal::forUserType('user'));
        $this->assertSame(Portal::Dealer, Portal::forUserType('garage'));
        $this->assertSame(Portal::Workshop, Portal::forUserType('workshop'));
        $this->assertSame(Portal::Owner, Portal::forUserType(null));
        $this->assertSame(Portal::Owner, Portal::forUserType('admin'));
        $this->assertSame(Portal::Owner, Portal::forUserType(''));
    }

    public function test_user_portal_and_type_label_use_the_glossary(): void
    {
        $this->assertSame(Portal::Owner, User::factory()->asUser()->make()->portal());
        $this->assertSame(Portal::Dealer, User::factory()->asGarage()->make()->portal());
        $this->assertSame(Portal::Workshop, User::factory()->asWorkshop()->make()->portal());
        $this->assertSame(Portal::Owner, User::factory()->asUser()->asAdmin()->make()->portal());

        $this->assertSame('Proprietário', User::factory()->asUser()->make()->typeLabel());
        $this->assertSame('Lojista', User::factory()->asGarage()->make()->typeLabel());
        $this->assertSame('Oficina', User::factory()->asWorkshop()->make()->typeLabel());
    }

    public function test_labels_title_labels_and_switch_labels(): void
    {
        $this->assertSame(
            ['Proprietário', 'Lojista', 'Oficina', 'Administrador'],
            array_map(fn (Portal $portal): string => $portal->label(), Portal::cases()),
        );
        $this->assertSame('Admin', Portal::Admin->titleLabel());
        $this->assertSame('Oficina', Portal::Workshop->titleLabel());
        $this->assertSame('Painel admin', Portal::Admin->switchLabel());
        $this->assertSame('Minha área de proprietário', Portal::Owner->switchLabel());
        $this->assertSame('Minha área de lojista', Portal::Dealer->switchLabel());
    }

    public function test_dashboard_vehicle_and_primary_action_routes_exist(): void
    {
        foreach (Portal::cases() as $portal) {
            $this->assertTrue(Route::has($portal->dashboardRoute()), "Rota de Início ausente: {$portal->name}");

            if ($portal->vehicleRoute() !== null) {
                $this->assertTrue(Route::has($portal->vehicleRoute()), "Ficha do veículo ausente: {$portal->name}");
            }

            if ($portal->primaryAction() !== null) {
                $this->assertTrue(Route::has($portal->primaryAction()['route']), "Ação principal ausente: {$portal->name}");
            }
        }

        $this->assertNull(Portal::Workshop->vehicleRoute());
        $this->assertSame('Adicionar veículo', Portal::Owner->primaryAction()['label']);
        $this->assertSame('Adicionar ao estoque', Portal::Dealer->primaryAction()['label']);
        $this->assertSame('Nova OS', Portal::Workshop->primaryAction()['label']);
        $this->assertNull(Portal::Admin->primaryAction());
    }

    public function test_portal_menus_follow_the_glossary_order_and_stay_within_five_items(): void
    {
        $labels = fn (Portal $portal): array => array_column($portal->navigation(), 'label');

        $this->assertSame(['Início', 'Meus veículos', 'Manutenções', 'Oficinas'], $labels(Portal::Owner));
        $this->assertSame(['Início', 'Estoque', 'Manutenções'], $labels(Portal::Dealer));
        $this->assertSame(['Início', 'Ordens de serviço', 'Validações', 'Modelos de garantia', 'Minha oficina'], $labels(Portal::Workshop));

        foreach ([Portal::Owner, Portal::Dealer, Portal::Workshop] as $portal) {
            $this->assertLessThanOrEqual(5, count($portal->navigation()));

            foreach ($portal->navigation() as $item) {
                $this->assertSame(['label', 'route', 'icon', 'activePatterns'], array_keys($item));
                $this->assertTrue(Route::has($item['route']), "Rota ausente no menu: {$item['route']}");
            }
        }
    }

    public function test_admin_navigation_is_grouped_and_every_route_exists(): void
    {
        $navigation = Portal::Admin->navigation();

        $this->assertSame('Visão geral', $navigation[0]['label']);
        $this->assertSame(
            ['Cadastros', 'Frota', 'Conteúdo', 'Catálogo'],
            array_column(array_slice($navigation, 1), 'label'),
        );

        foreach (array_slice($navigation, 1) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertTrue(Route::has($item['route']), "Rota ausente no admin: {$item['route']}");
            }
        }

        $items = Portal::Admin->navigationItems($this->requestFor(route('admin.brands.index', absolute: false)));
        $catalog = collect($items)->firstWhere('label', 'Catálogo');

        $this->assertSame(route('admin.users.index'), collect($items)->firstWhere('label', 'Cadastros')['items'][0]['href']);
        $this->assertTrue($catalog['items'][0]['active']);
        $this->assertFalse($items[0]['active']);
    }

    public function test_home_portal_of_an_account_is_admin_for_admins_and_the_user_type_otherwise(): void
    {
        $this->assertSame(Portal::Admin, Portal::homeFor(User::factory()->asUser()->asAdmin()->make()));
        $this->assertSame(Portal::Owner, Portal::homeFor(User::factory()->asUser()->make()));
        $this->assertSame(Portal::Dealer, Portal::homeFor(User::factory()->asGarage()->make()));
        $this->assertSame(Portal::Workshop, Portal::homeFor(User::factory()->asWorkshop()->make()));
    }

    public function test_every_portal_has_an_existing_login_route(): void
    {
        $this->assertSame(
            ['user' => 'login.usuario', 'garage' => 'login.lojista', 'workshop' => 'login.oficina', 'admin' => 'login.admin'],
            collect(Portal::cases())->mapWithKeys(fn (Portal $portal): array => [$portal->value => $portal->loginRoute()])->all(),
        );

        foreach (Portal::cases() as $portal) {
            $this->assertTrue(Route::has($portal->loginRoute()));
        }
    }

    public function test_portal_access_reads_home_label_and_login_from_the_portal(): void
    {
        $admin = User::factory()->asWorkshop()->asAdmin()->make();
        $dealer = User::factory()->asGarage()->make();

        $this->assertSame('admin.dashboard', PortalAccess::homeRouteName($admin));
        $this->assertSame('Administrador', PortalAccess::accountLabel($admin));
        $this->assertSame('login.admin', PortalAccess::loginRouteName($admin));
        $this->assertSame('garage.dashboard', PortalAccess::homeRouteName($dealer));
        $this->assertSame('Lojista', PortalAccess::accountLabel($dealer));
        $this->assertSame('login.lojista', PortalAccess::loginRouteName($dealer));
    }

    public function test_admin_maps_are_not_menu_items_and_activate_their_list(): void
    {
        $routes = collect(Portal::Admin->navigation())
            ->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry])
            ->pluck('route');

        $this->assertNotContains('admin.maps.workshops', $routes);
        $this->assertNotContains('admin.maps.users', $routes);

        $workshopsMap = collect(Portal::Admin->navigationItems($this->requestFor(route('admin.maps.workshops', absolute: false))))
            ->firstWhere('label', 'Cadastros')['items'];
        $usersMap = collect(Portal::Admin->navigationItems($this->requestFor(route('admin.maps.users', absolute: false))))
            ->firstWhere('label', 'Cadastros')['items'];

        $this->assertSame(['Usuários' => false, 'Oficinas' => true, 'Prospecção' => false], array_column($workshopsMap, 'active', 'label'));
        $this->assertSame(['Usuários' => true, 'Oficinas' => false, 'Prospecção' => false], array_column($usersMap, 'active', 'label'));
    }

    public function test_navigation_items_resolve_hrefs_and_mark_the_active_route_family(): void
    {
        $items = Portal::Owner->navigationItems($this->requestFor(route('user.vehicles.create', absolute: false)));

        $this->assertSame(route('user.dashboard'), $items[0]['href']);
        $this->assertSame('home', $items[0]['icon']);
        $this->assertSame(
            ['Início' => false, 'Meus veículos' => true, 'Manutenções' => false, 'Oficinas' => false],
            array_column($items, 'active', 'label'),
        );

        $workshopItems = Portal::Workshop->navigationItems($this->requestFor(route('workshop.warranty-templates.create', absolute: false)));

        $this->assertTrue(collect($workshopItems)->firstWhere('label', 'Modelos de garantia')['active']);
        $this->assertFalse(collect($workshopItems)->firstWhere('label', 'Início')['active']);
    }

    public function test_current_portal_is_admin_only_on_admin_routes_for_admins(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->make();
        $owner = User::factory()->asUser()->make();

        $this->assertNull(Portal::current(null, $this->requestFor(route('home', absolute: false))));
        $this->assertSame(Portal::Admin, Portal::current($admin, $this->requestFor(route('admin.brands.index', absolute: false))));
        $this->assertSame(Portal::Owner, Portal::current($admin, $this->requestFor(route('user.vehicles.index', absolute: false))));
        $this->assertSame(Portal::Owner, Portal::current($owner, $this->requestFor(route('admin.brands.index', absolute: false))));
    }

    public function test_accessible_areas_add_the_admin_panel_only_for_admins(): void
    {
        $this->assertSame([Portal::Dealer], Portal::accessibleBy(User::factory()->asGarage()->make()));
        $this->assertSame([Portal::Owner, Portal::Admin], Portal::accessibleBy(User::factory()->asUser()->asAdmin()->make()));
    }

    private function requestFor(string $uri): Request
    {
        $request = Request::create($uri);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
