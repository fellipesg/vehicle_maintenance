<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\DocumentTitle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Shell unificado de layouts.app: topbar (destinos do portal, ação principal, busca, sino e menu de
 * conta), menu mobile em <x-ui.sheet> e topbar pública, tudo a partir de App\Enums\Portal.
 */
class PortalShellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: string, 4: string, 5: string, 6: string}>
     */
    public static function portals(): array
    {
        return [
            'proprietário' => ['asUser', 'user.vehicles.index', ['Início', 'Meus veículos', 'Manutenções', 'Oficinas'], 'Meus veículos', 'Adicionar veículo', 'user.vehicles.create', 'Proprietário'],
            'lojista' => ['asGarage', 'garage.vehicles.index', ['Início', 'Estoque', 'Manutenções'], 'Estoque', 'Adicionar ao estoque', 'garage.vehicles.create', 'Lojista'],
            'oficina' => ['asWorkshop', 'workshop.maintenances.index', ['Início', 'Ordens de serviço', 'Validações', 'Modelos de garantia', 'Minha oficina'], 'Ordens de serviço', 'Nova OS', 'workshop.maintenances.create', 'Oficina'],
        ];
    }

    /**
     * @param  list<string>  $expectedItems
     */
    #[DataProvider('portals')]
    public function test_topbar_lists_the_portal_destinations_with_the_active_one_marked(
        string $factoryState,
        string $routeName,
        array $expectedItems,
        string $activeItem,
        string $actionLabel,
        string $actionRoute,
        string $portalLabel,
    ): void {
        $user = User::factory()->{$factoryState}()->create();
        $page = $this->page($this->actingAs($user)->get(route($routeName))->assertOk());

        $links = $page->query('//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a');

        $this->assertSame($expectedItems, array_map(fn (DOMElement $link): string => $this->text($link), iterator_to_array($links)));

        $current = $page->query('//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a[@aria-current="page"]');

        $this->assertSame(1, $current->length);
        $this->assertSame($activeItem, $this->text($current->item(0)));
        $this->assertSame(route($user->portal()->dashboardRoute()), $this->element($page, '//a[@data-shell-logo]')->getAttribute('href'));
    }

    /**
     * @param  list<string>  $expectedItems
     */
    #[DataProvider('portals')]
    public function test_topbar_and_mobile_menu_show_the_portal_primary_action(
        string $factoryState,
        string $routeName,
        array $expectedItems,
        string $activeItem,
        string $actionLabel,
        string $actionRoute,
        string $portalLabel,
    ): void {
        $user = User::factory()->{$factoryState}()->create();
        $page = $this->page($this->actingAs($user)->get(route($routeName))->assertOk());

        $actions = $page->query('//a[@data-shell-primary-action]');

        $this->assertSame(2, $actions->length, 'A ação principal vai na topbar e no topo do menu mobile.');

        foreach ($actions as $action) {
            $this->assertSame($actionLabel, $this->text($action));
            $this->assertSame(route($actionRoute), $action->getAttribute('href'));
            $this->assertSame('primary', $action->getAttribute('data-variant'));
        }
    }

    /**
     * @param  list<string>  $expectedItems
     */
    #[DataProvider('portals')]
    public function test_account_menu_groups_account_actions_behind_the_avatar(
        string $factoryState,
        string $routeName,
        array $expectedItems,
        string $activeItem,
        string $actionLabel,
        string $actionRoute,
        string $portalLabel,
    ): void {
        $user = User::factory()->{$factoryState}()->create(['name' => 'Ana Paula de Souza', 'email' => 'ana@example.com']);
        $page = $this->page($this->actingAs($user)->get(route($routeName))->assertOk());

        $trigger = $this->element($page, '//*[@data-account-menu]/button');

        $this->assertSame('Conta de Ana Paula de Souza', $trigger->getAttribute('aria-label'));
        $this->assertSame('menu', $trigger->getAttribute('aria-haspopup'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
        $this->assertSame('AS', $this->text($this->element($page, '//*[@data-account-menu]/button//*[@data-slot="avatar-initials"]')));

        $header = $this->text($this->element($page, '//*[@data-account-menu-header]'));

        $this->assertStringContainsString('Ana Paula de Souza', $header);
        $this->assertStringContainsString('ana@example.com', $header);
        $this->assertStringContainsString($portalLabel, $header);

        $menuItems = array_map(
            fn (DOMElement $item): string => $this->text($item),
            iterator_to_array($page->query('//*[@data-account-menu]//*[@role="menuitem"]')),
        );

        $this->assertSame(
            array_values(array_filter([Route::has('account.edit') ? 'Minha conta' : null, 'Notificações', 'Ajuda e contato', 'Termos e privacidade', 'Sair'])),
            $menuItems,
        );
        $this->assertSame(route('contact.show'), $this->element($page, '//*[@data-account-menu]//a[normalize-space()="Ajuda e contato"]')->getAttribute('href'));
        $this->assertSame(route('legal.terms'), $this->element($page, '//*[@data-account-menu]//a[normalize-space()="Termos e privacidade"]')->getAttribute('href'));
        $this->assertSame(route('logout'), $this->element($page, '//*[@data-account-menu]//form[.//button[normalize-space()="Sair"]]')->getAttribute('action'));
        $this->assertSame('POST', strtoupper($this->element($page, '//*[@data-account-menu]//form[.//button[normalize-space()="Sair"]]')->getAttribute('method')));
        $this->assertStringNotContainsString('Trocar de área', $this->text($this->element($page, '//*[@data-account-menu]')));
        $this->assertSame(0, $page->query('//a[@href="'.route('admin.dashboard').'"]')->length, 'Só admin vê o Painel admin.');
    }

    public function test_account_menu_links_to_minha_conta_when_the_route_exists(): void
    {
        if (! Route::has('account.edit')) {
            $this->markTestSkipped('A rota account.edit ainda não existe.');
        }

        $page = $this->page($this->actingAs(User::factory()->asGarage()->create())->get(route('garage.dashboard'))->assertOk());

        $this->assertSame(route('account.edit'), $this->element($page, '//*[@data-account-menu]//a[normalize-space()="Minha conta"]')->getAttribute('href'));
        $this->assertSame(1, $page->query('//*[@id="menu-mobile"]//a[@href="'.route('account.edit').'"]')->length);
    }

    public function test_header_has_no_loose_name_portal_badge_or_sign_out_button(): void
    {
        $user = User::factory()->asWorkshop()->create(['name' => 'Oficina Central Ltda']);
        $response = $this->actingAs($user)->get(route('workshop.dashboard'))->assertOk();
        $page = $this->page($response);

        // Sair só no menu de conta e no rodapé do menu mobile.
        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('logout').'"'));
        $this->assertSame(0, $page->query('//header[@data-shell-topbar]//*[contains(concat(" ", normalize-space(@class), " "), " badge ")]')->length);

        $topbarText = $this->text($this->element($page, '//header[@data-shell-topbar]'));
        $outsideMenus = str_replace(
            [$this->text($this->element($page, '//*[@data-account-menu]')), $this->text($this->element($page, '//*[@data-notification-bell]'))],
            '',
            $topbarText,
        );

        $this->assertStringNotContainsString('Oficina Central Ltda', $outsideMenus);
        $this->assertStringNotContainsString('Sair', $outsideMenus);
        $this->assertStringNotContainsString('Painel admin', $topbarText);
    }

    public function test_admin_account_can_switch_to_the_admin_panel_from_the_account_menu_only(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $page = $this->page($this->actingAs($admin)->get(route('user.dashboard'))->assertOk());

        $switch = $this->element($page, '//*[@data-account-menu]//a[@data-area-switch="admin"]');

        $this->assertSame('Painel admin', $this->text($switch));
        $this->assertSame(route('admin.dashboard'), $switch->getAttribute('href'));
        $this->assertStringContainsString('Trocar de área', $this->text($this->element($page, '//*[@data-account-menu]')));
        $this->assertSame(0, $page->query('//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a[@href="'.route('admin.dashboard').'"]')->length);
        $this->assertSame(route('admin.dashboard'), $this->element($page, '//*[@id="menu-mobile"]//a[@data-area-switch="admin"]')->getAttribute('href'));
    }

    public function test_search_trigger_is_always_present_with_an_accessible_name(): void
    {
        $owner = User::factory()->asUser()->create();

        $dashboard = $this->page($this->actingAs($owner)->get(route('user.dashboard'))->assertOk());
        $search = $this->element($dashboard, '//a[@data-shell-search]');

        $this->assertSame(route('vehicle.search'), $search->getAttribute('href'));
        $this->assertSame('Buscar veículo', $this->text($search));
        $this->assertStringContainsString('sr-only xl:not-sr-only', $this->element($dashboard, '//a[@data-shell-search]/span')->getAttribute('class'));
        $this->assertStringNotContainsString('hidden', $search->getAttribute('class'));
        $this->assertFalse($search->hasAttribute('aria-current'));

        $searchPage = $this->page($this->actingAs($owner)->get(route('vehicle.search'))->assertOk());

        $this->assertSame('page', $this->element($searchPage, '//a[@data-shell-search]')->getAttribute('aria-current'));
    }

    public function test_mobile_menu_is_a_sheet_with_the_same_destinations_and_the_account(): void
    {
        $garage = User::factory()->asGarage()->create(['name' => 'Loja Bom Carro']);
        $garage->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\MaintenanceKmReminderNotification',
            'data' => ['title' => 'Revisão'],
        ]);
        $page = $this->page($this->actingAs($garage)->get(route('garage.maintenances.index'))->assertOk());

        $trigger = $this->element($page, '//header[@data-shell-topbar]//button[@aria-controls="menu-mobile"]');

        $this->assertSame('Abrir menu', $trigger->getAttribute('aria-label'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
        $this->assertSame('dialog', $trigger->getAttribute('aria-haspopup'));
        $this->assertSame('#menu-mobile', $trigger->getAttribute('data-hs-overlay'));

        $sheet = $this->element($page, '//*[@id="menu-mobile"]');

        $this->assertTrue($sheet->hasAttribute('data-ui-sheet'));
        $this->assertTrue($sheet->hasAttribute('data-shell-overlay'));
        $this->assertSame('dialog', $sheet->getAttribute('role'));
        $this->assertSame('true', $sheet->getAttribute('aria-modal'));
        $this->assertStringContainsString('theme-inverse', $sheet->getAttribute('class'));
        $this->assertStringContainsString('lg:hidden', $sheet->getAttribute('class'));

        $sheetItems = array_map(
            fn (DOMElement $link): string => $this->text($link),
            iterator_to_array($page->query('//*[@id="menu-mobile"]//nav[@aria-label="Menu do portal"]//a')),
        );

        $this->assertSame(['Início', 'Estoque', 'Manutenções'], $sheetItems);
        $this->assertSame('Manutenções', $this->text($this->element($page, '//*[@id="menu-mobile"]//nav[@aria-label="Menu do portal"]//a[@aria-current="page"]')));

        $accountBlock = $this->text($this->element($page, '//*[@data-mobile-nav-account]'));

        $this->assertStringContainsString('Loja Bom Carro', $accountBlock);
        $this->assertStringContainsString('Lojista', $accountBlock);

        $accountLinks = array_map(
            fn (DOMElement $link): string => $this->text($link),
            iterator_to_array($page->query('//*[@id="menu-mobile"]//nav[@aria-labelledby="menu-mobile-conta"]//a')),
        );

        $this->assertSame(
            array_values(array_filter(['Buscar veículo', 'Notificações 1 não lida', Route::has('account.edit') ? 'Minha conta' : null, 'Ajuda e contato', 'Termos e privacidade'])),
            $accountLinks,
        );
        $this->assertSame(1, $page->query('//*[@id="menu-mobile"]//form[@action="'.route('logout').'"]//button[normalize-space()="Sair"]')->length);
    }

    public function test_visitor_topbar_has_landing_anchors_only_on_the_home_page(): void
    {
        $home = $this->page($this->get(route('home'))->assertOk());

        $this->assertSame(
            ['Como funciona', 'Procedência', 'Produto', 'Para quem', 'Preço', 'Blog'],
            array_map(fn (DOMElement $link): string => $this->text($link), iterator_to_array($home->query('//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a'))),
        );
        // Entre lg e xl as seis âncoras não cabem com folga ao lado de Entrar e "Começar grátis".
        $this->assertSame('lg:max-xl:hidden', $this->element($home, '//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a[@href="#produto"]/parent::li')->getAttribute('class'));
        $this->assertSame(route('home'), $this->element($home, '//a[@data-shell-logo]')->getAttribute('href'));
        $this->assertSame(route('login'), $this->element($home, '//header[@data-shell-topbar]//a[normalize-space()="Entrar"]')->getAttribute('href'));
        $this->assertSame(route('register'), $this->element($home, '//header[@data-shell-topbar]//a[normalize-space()="Começar grátis"]')->getAttribute('href'));
        $this->assertSame(0, $home->query('//*[@data-account-menu]')->length);
        $this->assertSame(0, $home->query('//*[@data-notification-bell]')->length);
        $this->assertSame(0, $home->query('//a[@data-shell-search]')->length);

        $sheetFooter = $this->text($this->element($home, '//*[@id="menu-mobile"]'));

        $this->assertStringContainsString('Começar grátis', $sheetFooter);
        $this->assertStringContainsString('Entrar', $sheetFooter);

        $blog = $this->page($this->get(route('blog.index'))->assertOk());
        $blogLinks = $blog->query('//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a');

        $this->assertSame(6, $blogLinks->length);
        $this->assertSame(route('home').'#como-funciona', $this->element($blog, '//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a[normalize-space()="Como funciona"]')->getAttribute('href'));
        $this->assertSame('page', $this->element($blog, '//header[@data-shell-topbar]//nav[@aria-label="Principal"]//a[normalize-space()="Blog"]')->getAttribute('aria-current'));
    }

    public function test_logged_in_user_on_a_public_page_gets_the_portal_shell_without_sign_up(): void
    {
        $owner = User::factory()->asUser()->create();
        $response = $this->actingAs($owner)->get(route('blog.index'))->assertOk();
        $page = $this->page($response);

        $this->assertSame(route('user.dashboard'), $this->element($page, '//a[@data-shell-logo]')->getAttribute('href'));
        $this->assertSame(1, $page->query('//*[@data-account-menu]')->length);
        $this->assertSame(0, $page->query('//header[@data-shell-topbar]//a[normalize-space()="Começar grátis"]')->length);
        $this->assertStringContainsString('<title>Blog · '.DocumentTitle::BRAND.'</title>', $response->getContent());
    }

    public function test_layout_renders_toaster_confirm_dialog_and_flash_once(): void
    {
        $owner = User::factory()->asUser()->create();
        $response = $this->actingAs($owner)
            ->withSession(['toast' => 'Veículo salvo.', 'success' => 'Manutenção registrada.'])
            ->get(route('user.dashboard'))
            ->assertOk();
        $page = $this->page($response);

        $this->assertSame(1, $page->query('//*[@data-ui-toaster]')->length);
        $this->assertStringContainsString('Veículo salvo.', $this->element($page, '//*[@data-ui-toaster]')->getAttribute('data-toasts'));
        $this->assertSame(1, $page->query('//dialog[@id="confirmacao"]')->length);
        $this->assertSame(1, $page->query('//*[@data-flash="success"]')->length);
        $this->assertSame('status', $this->element($page, '//*[@data-flash="success"]')->getAttribute('role'));
    }

    public function test_titles_compose_page_area_and_brand(): void
    {
        $brand = DocumentTitle::BRAND;

        $this->assertStringContainsString(
            "<title>Início · Lojista · {$brand}</title>",
            $this->actingAs(User::factory()->asGarage()->create())->get(route('garage.dashboard'))->getContent(),
        );
        $this->assertStringContainsString(
            "<title>Notificações · Oficina · {$brand}</title>",
            $this->actingAs(User::factory()->asWorkshop()->create())->get(route('notifications.index'))->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/<title>[^<]+ · Proprietário · '.preg_quote($brand, '/').'<\/title>/u',
            $this->actingAs(User::factory()->asUser()->create())->get(route('vehicle.search'))->getContent(),
        );
    }

    private function page(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function element(DOMXPath $page, string $query): DOMElement
    {
        $element = $page->query($query)->item(0);

        $this->assertInstanceOf(DOMElement::class, $element, "Nenhum elemento em {$query}.");

        return $element;
    }

    private function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}
