<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\DocumentTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_has_menu_button_and_sheet_with_landing_links(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response
            ->assertSee('aria-controls="menu-mobile"', false)
            ->assertSee('data-hs-overlay="#menu-mobile"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Abrir menu');

        $menu = $this->mobileMenu($response);

        $this->assertStringContainsString('role="dialog"', $menu);
        $this->assertStringContainsString('aria-label="Fechar"', $menu);

        foreach (['#como-funciona', '#procedencia', '#produto', '#para-quem', '#preco', route('blog.index'), route('login'), route('register')] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $menu);
        }

        $this->assertStringContainsString('Começar grátis', $menu);
        $this->assertStringNotContainsString('Sair', $menu);
        $this->assertStringNotContainsString(route('user.dashboard'), $menu);
    }

    public function test_guest_menu_outside_home_has_blog_marked_as_current_and_no_landing_anchors(): void
    {
        $menu = $this->mobileMenu($this->get(route('blog.index'))->assertOk());

        $this->assertStringNotContainsString('#como-funciona', $menu);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('blog.index'), '/').'"\s+aria-current="page"/', $menu);
    }

    public function test_owner_menu_lists_owner_portal_items_and_account_actions(): void
    {
        $owner = User::factory()->asUser()->create();

        $response = $this->actingAs($owner)->get(route('user.dashboard'))->assertOk();
        $menu = $this->mobileMenu($response);

        foreach (['user.dashboard', 'user.vehicles.index', 'user.maintenances.index', 'user.workshops.index', 'vehicle.search', 'notifications.index'] as $routeName) {
            $this->assertStringContainsString('href="'.route($routeName).'"', $menu);
        }

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('user.dashboard'), '/').'"\s+aria-current="page"/', $menu);
        $this->assertStringContainsString('Proprietário', $menu);
        $this->assertStringContainsString('action="'.route('logout').'"', $menu);
        $this->assertStringContainsString('Sair', $menu);
        $this->assertStringNotContainsString(route('admin.dashboard'), $menu);
        $this->assertStringNotContainsString(route('garage.vehicles.index'), $menu);
        $this->assertStringNotContainsString('Começar grátis', $menu);
    }

    public function test_owner_who_is_admin_gets_admin_panel_link_in_menu(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $menu = $this->mobileMenu($this->actingAs($admin)->get(route('user.dashboard'))->assertOk());

        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $menu);
        $this->assertStringContainsString('Painel admin', $menu);
    }

    public function test_garage_menu_lists_garage_portal_items(): void
    {
        $garage = User::factory()->asGarage()->create();

        $menu = $this->mobileMenu($this->actingAs($garage)->get(route('garage.dashboard'))->assertOk());

        foreach (['garage.dashboard', 'garage.vehicles.index', 'garage.maintenances.index', 'vehicle.search', 'notifications.index'] as $routeName) {
            $this->assertStringContainsString('href="'.route($routeName).'"', $menu);
        }

        $this->assertStringContainsString('Lojista', $menu);
        $this->assertStringNotContainsString(route('user.vehicles.index'), $menu);
        $this->assertStringNotContainsString(route('workshop.maintenances.index'), $menu);
    }

    public function test_workshop_menu_lists_workshop_portal_items(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        $menu = $this->mobileMenu($this->actingAs($workshop)->get(route('workshop.maintenances.index'))->assertOk());

        foreach (['workshop.dashboard', 'workshop.profile.show', 'workshop.maintenances.index', 'workshop.warranty-templates.index'] as $routeName) {
            $this->assertStringContainsString('href="'.route($routeName).'"', $menu);
        }

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('workshop.maintenances.index'), '/').'"\s+aria-current="page"/', $menu);
        $this->assertStringNotContainsString(route('garage.vehicles.index'), $menu);
    }

    public function test_layouts_have_skip_link_and_main_landmark(): void
    {
        $owner = User::factory()->asUser()->create();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $responses = [
            $this->get(route('home')),
            $this->get(route('login.usuario')),
            $this->actingAs($owner)->get(route('user.dashboard')),
            $this->actingAs($admin)->get(route('admin.dashboard')),
        ];

        foreach ($responses as $response) {
            $response->assertOk()
                ->assertSee('<a href="#conteudo" class="skip-link">Pular para o conteúdo</a>', false)
                ->assertSee('<main id="conteudo" tabindex="-1"', false);
        }
    }

    public function test_admin_layout_uses_preline_overlay_instead_of_checkbox_hack(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response
            ->assertSee('id="nav-admin"', false)
            ->assertSee('data-hs-overlay="#nav-admin"', false)
            ->assertSee('aria-controls="nav-admin"', false)
            ->assertSee('aria-label="Fechar menu"', false)
            ->assertSee('aria-label="Administração"', false)
            ->assertDontSee('admin-sidebar-toggle', false)
            ->assertDontSee('peer-checked', false);

        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<svg\b.*?<\/svg>\s*<span[^>]*>Visão geral<\/span>/su', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('logout').'"'));
    }

    public function test_error_flash_is_rendered_as_alert_with_dismiss_button(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        $this->actingAs($workshop)
            ->withSession(['error' => 'Cadastre sua oficina antes de registrar serviços.'])
            ->get(route('workshop.dashboard'))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('data-flash="error"', false)
            ->assertSee('Cadastre sua oficina antes de registrar serviços.')
            ->assertSee('aria-label="Fechar aviso"', false);
    }

    public function test_success_flash_is_announced_as_status(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->withSession(['success' => 'Veículo cadastrado com sucesso!'])
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee('data-flash="success"', false)
            ->assertSee('Veículo cadastrado com sucesso!');
    }

    public function test_flash_is_rendered_in_admin_and_guest_layouts(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)
            ->withSession(['warning' => 'Revise os dados da marca.'])
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('data-flash="warning"', false)
            ->assertSee('Revise os dados da marca.');

        auth()->logout();

        $this->withSession(['error' => 'Sessão expirada. Entre de novo.'])
            ->get(route('login.usuario'))
            ->assertOk()
            ->assertSee('data-flash="error"', false)
            ->assertSee('Sessão expirada. Entre de novo.');
    }

    public function test_titles_follow_page_area_brand_pattern(): void
    {
        $owner = User::factory()->asUser()->create();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->assertMatchesRegularExpression('/<title>[^<]+ · '.preg_quote(DocumentTitle::BRAND, '/').'<\/title>/', $this->get(route('blog.index'))->getContent());
        $this->assertStringContainsString('<title>Blog · '.DocumentTitle::BRAND.'</title>', $this->get(route('blog.index'))->getContent());
        $this->assertStringContainsString(
            '<title>Notificações · Proprietário · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs($owner)->get(route('notifications.index'))->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/<title>[^<]+ · Admin · '.preg_quote(DocumentTitle::BRAND, '/').'<\/title>/u',
            $this->actingAs($admin)->get(route('admin.brands.index'))->getContent(),
        );
        $this->assertStringNotContainsString('Histórico de Manutenções</title>', $this->actingAs($owner)->get(route('user.dashboard'))->getContent());
    }

    public function test_dashboard_titles_use_the_glossary_term_for_the_home_of_each_area(): void
    {
        $this->assertStringContainsString(
            '<title>Início · Proprietário · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs(User::factory()->asUser()->create())->get(route('user.dashboard'))->getContent(),
        );
        $this->assertStringContainsString(
            '<title>Início · Lojista · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs(User::factory()->asGarage()->create())->get(route('garage.dashboard'))->getContent(),
        );
        $this->assertStringContainsString(
            '<title>Início · Oficina · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs(User::factory()->asWorkshop()->create())->get(route('workshop.dashboard'))->getContent(),
        );
        $this->assertStringContainsString(
            '<title>Visão geral · Admin · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs(User::factory()->asUser()->asAdmin()->create())->get(route('admin.dashboard'))->getContent(),
        );
    }

    public function test_portal_menus_call_the_home_page_inicio(): void
    {
        foreach ([
            [User::factory()->asUser()->create(), 'user.dashboard'],
            [User::factory()->asGarage()->create(), 'garage.dashboard'],
            [User::factory()->asWorkshop()->create(), 'workshop.dashboard'],
        ] as [$user, $routeName]) {
            $menu = $this->mobileMenu($this->actingAs($user)->get(route($routeName))->assertOk());

            $this->assertMatchesRegularExpression('/href="'.preg_quote(route($routeName), '/').'"\s+aria-current="page"[^>]*>\s*(?:<svg\b.*?<\/svg>\s*)?<span[^>]*>Início<\/span>/us', $menu);
            $this->assertStringNotContainsString('Dashboard', $menu);
        }
    }

    public function test_views_pass_only_the_page_name_as_title(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->assertStringContainsString(
            '<title>Veículos · Admin · '.DocumentTitle::BRAND.'</title>',
            $this->actingAs($admin)->get(route('admin.vehicles.index'))->getContent(),
        );
        $this->assertStringContainsString('<title>Contato · '.DocumentTitle::BRAND.'</title>', $this->get(route('contact.show'))->getContent());

        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match("/@section\('title',[^\n]*(?:— (?:Admin|Revisa[lL]og)|'Dashboard|'Painel Admin)/u", $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'A view deve passar só o nome da página; o layout compõe área e marca.');
    }

    public function test_preline_plugins_are_initialized_by_the_bundle_not_by_the_layout(): void
    {
        $bundle = file_get_contents(resource_path('js/app.js'));
        $prelineSetup = file_get_contents(resource_path('js/ui/preline.js'));
        $shellScript = file_get_contents(resource_path('views/layouts/partials/shell-script.blade.php'));

        // Fase 1: só os plugins usados (overlay e dropdown), iniciados por initPreline() no carregamento.
        $this->assertStringContainsString("import { initPreline } from './ui/preline';", $bundle);
        $this->assertMatchesRegularExpression('/function initApp\(\) \{\s*initPreline\(\);/', $bundle);
        $this->assertStringContainsString("import HSOverlay from 'preline/plugins/overlay-non-auto';", $prelineSetup);
        $this->assertStringContainsString('HSOverlay.autoInit();', $prelineSetup);
        $this->assertStringNotContainsString('autoInit', $shellScript);
    }

    public function test_workshop_menu_calls_warranty_templates_modelos(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        $this->actingAs($workshop)
            ->get(route('workshop.warranty-templates.index'))
            ->assertOk()
            ->assertSee('<title>Modelos de garantia · Oficina · '.DocumentTitle::BRAND.'</title>', false)
            ->assertSee('Modelos de garantia')
            ->assertSee('Novo modelo')
            ->assertDontSee('Templates de garantia')
            ->assertDontSee('Novo template');
    }

    public function test_logged_in_users_get_compact_footer_and_guests_get_full_footer(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee(route('login.lojista'), false)
            ->assertSee(config('legal.support_email'))
            ->assertSeeInOrder(['Conferir selo da oficina', route('blog.index')], false)
            ->assertSee('href="'.route('verification.lookup').'"', false);

        $this->actingAs($owner)
            ->get(route('blog.index'))
            ->assertOk()
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('contact.show'), false)
            ->assertDontSee(route('login.lojista'), false)
            ->assertDontSee(route('home').'#preco', false);
    }

    public function test_notification_bell_has_accessible_name_and_only_links_vehicles_when_possible(): void
    {
        $owner = User::factory()->asUser()->create();
        $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MaintenanceKmReminderNotification',
            'data' => ['title' => 'Revisão dos 40.000 km', 'vehicle_id' => 123],
        ]);
        $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\WorkshopFollowUpNotification',
            'data' => ['title' => 'Mensagem da oficina'],
        ]);

        $response = $this->actingAs($owner)->get(route('user.dashboard'))->assertOk();

        $response
            ->assertSee('<span class="sr-only">Notificações, 2 não lidas</span>', false)
            ->assertSee('data-notification-bell', false)
            ->assertSee('Revisão dos 40.000 km')
            ->assertSee('Mensagem da oficina');

        $this->assertSame(1, substr_count($response->getContent(), 'Ver veículo'));
        $this->assertStringContainsString('<span class="sr-only"> não lidas</span>', $this->mobileMenu($response));
    }

    public function test_notification_bell_without_unread_items_is_still_named(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('<span class="sr-only">Notificações</span>', false)
            ->assertSee('Nenhuma notificação nova.');
    }

    private function mobileMenu(TestResponse $response): string
    {
        $html = $response->getContent();
        $start = strpos($html, 'id="menu-mobile"');

        $this->assertNotFalse($start, 'O menu mobile não foi renderizado.');

        $end = strpos($html, '<main id="conteudo"', $start);

        return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
    }
}
