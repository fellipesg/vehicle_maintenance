<?php

namespace Tests\Feature\Web;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Paleta de comandos (Ctrl K / ⌘K) no admin e nos portais (ADM-15, NAV-18): uma por página, com os
 * destinos do App\Enums\Portal, as ações principais e "Buscar veículo por placa, chassi ou RENAVAM"
 * apontando para a busca que já existe (lista de veículos no admin, /buscar-veiculo nos portais).
 * Os gatilhos continuam links para a busca quando não há JS.
 */
class CommandPaletteShellTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_admin_palette_lists_the_admin_destinations_actions_and_the_fleet_search(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.maintenances.index')));

        $this->assertSame(1, $xpath->query('//dialog[@data-ui-command]')->length);
        $palette = $this->adminElement($xpath, '//dialog[@data-ui-command][@id="comandos"]');

        $this->assertSame(
            ['Visão geral', 'Usuários', 'Oficinas', 'Veículos', 'Manutenções', 'Artigos do blog', 'Categorias do blog', 'Marcas e modelos'],
            $this->optionLabels($xpath, $palette, 'destinos'),
        );
        $this->assertSame(['Novo artigo', 'Nova marca', 'Minha área de proprietário'], $this->optionLabels($xpath, $palette, 'acoes'));

        $vehicles = $this->adminElement($xpath, './/*[@data-command-group="destinos"]//*[@role="option"][.//*[@data-command-label][normalize-space()="Veículos"]]', $palette);
        $this->assertSame(route('admin.vehicles.index'), $vehicles->getAttribute('data-command-url'));
        $this->assertSame('Frota', $vehicles->getAttribute('data-command-keywords'));

        $current = $this->adminElement($xpath, './/*[@role="option"][.//*[normalize-space()="Página atual"]]', $palette);
        $this->assertStringContainsString('Manutenções', $this->adminText($current));

        $newBrand = $this->adminElement($xpath, './/*[@data-command-group="acoes"]//*[@role="option"][.//*[normalize-space()="Nova marca"]]', $palette);
        $this->assertSame(route('admin.brands.create'), $newBrand->getAttribute('data-command-url'));

        $search = $this->adminElement($xpath, './/*[@data-command-search]', $palette);
        $this->assertSame(route('admin.vehicles.index'), $search->getAttribute('data-command-url'));
        $this->assertSame('search', $search->getAttribute('data-command-param'), 'A lista de veículos do admin já filtra por ?search=.');
    }

    public function test_admin_topbar_has_a_keyboard_hinted_trigger_and_the_mobile_search_opens_the_palette(): void
    {
        $xpath = $this->adminPage($this->actingAs(User::factory()->asUser()->asAdmin()->create())->get(route('admin.dashboard')));

        $trigger = $this->adminElement($xpath, '//header[@data-admin-topbar]//button[@data-admin-command-trigger]');
        $this->assertSame('comandos', $trigger->getAttribute('data-command-open'));
        $this->assertSame('Control+K', $trigger->getAttribute('aria-keyshortcuts'));
        $this->assertStringContainsString('lg:inline-flex', $trigger->getAttribute('class'));
        $this->assertStringContainsString('motion-reduce:transition-none', $trigger->getAttribute('class'));

        $shortcut = $this->adminElement($xpath, './/kbd[@data-command-shortcut]', $trigger);
        $this->assertSame('Ctrl K', $this->adminText($shortcut));
        $this->assertSame('true', $shortcut->getAttribute('aria-hidden'), 'O atalho está em aria-keyshortcuts; o nome do botão é "Ir para…".');
        $this->assertSame('Ir para…', $this->visibleName($trigger));

        $mobileSearch = $this->adminElement($xpath, '//header[@data-admin-topbar]//a[@aria-label="Buscar veículo"]');
        $this->assertSame(route('admin.vehicles.index'), $mobileSearch->getAttribute('href'), 'Sem JS continua indo para a lista de veículos.');
        $this->assertSame('comandos', $mobileSearch->getAttribute('data-command-open'));
    }

    public function test_palette_stays_available_on_the_vehicle_list_without_a_second_search_field(): void
    {
        $xpath = $this->adminPage($this->actingAs(User::factory()->asUser()->asAdmin()->create())->get(route('admin.vehicles.index')));

        $this->assertSame(1, $xpath->query('//dialog[@data-ui-command]')->length);
        $this->assertSame(1, $xpath->query('//header[@data-admin-topbar]//button[@data-admin-command-trigger]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="search"]')->length);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: string, 4: string}>
     */
    public static function portals(): array
    {
        return [
            'proprietário' => ['asUser', 'user.dashboard', ['Início', 'Meus veículos', 'Manutenções', 'Oficinas'], 'Adicionar veículo', 'user.vehicles.create'],
            'lojista' => ['asGarage', 'garage.dashboard', ['Início', 'Estoque', 'Manutenções'], 'Adicionar ao estoque', 'garage.vehicles.create'],
            'oficina' => ['asWorkshop', 'workshop.dashboard', ['Início', 'Ordens de serviço', 'Validações', 'Modelos de garantia', 'Minha oficina'], 'Nova OS', 'workshop.maintenances.create'],
        ];
    }

    /**
     * @param  list<string>  $destinations
     */
    #[DataProvider('portals')]
    public function test_portal_palette_lists_the_portal_destinations_primary_action_and_vehicle_search(
        string $factoryState,
        string $routeName,
        array $destinations,
        string $actionLabel,
        string $actionRoute,
    ): void {
        $user = User::factory()->{$factoryState}()->create();
        $xpath = $this->page($this->actingAs($user)->get(route($routeName)));

        $this->assertSame(1, $xpath->query('//dialog[@data-ui-command]')->length);
        $palette = $this->adminElement($xpath, '//dialog[@data-ui-command][@id="comandos"]');
        $this->assertSame(0, $xpath->query('ancestor::header', $palette)->length, 'A paleta fica fora da topbar.');

        $this->assertSame($destinations, $this->optionLabels($xpath, $palette, 'destinos'));
        $this->assertSame([$actionLabel], $this->optionLabels($xpath, $palette, 'acoes'));
        $this->assertSame(
            route($actionRoute),
            $this->adminElement($xpath, './/*[@data-command-group="acoes"]//*[@role="option"]', $palette)->getAttribute('data-command-url'),
        );

        $search = $this->adminElement($xpath, './/*[@data-command-search]', $palette);
        $this->assertSame(route('vehicle.search'), $search->getAttribute('data-command-url'));
        $this->assertSame('identifier', $search->getAttribute('data-command-param'), 'A busca pública lê ?identifier=.');

        $trigger = $this->adminElement($xpath, '//header[@data-shell-topbar]//a[@data-shell-search]');
        $this->assertSame('comandos', $trigger->getAttribute('data-command-open'));
        $this->assertSame(route('vehicle.search'), $trigger->getAttribute('href'));
        $this->assertSame('Buscar veículo', $this->adminText($trigger));

        $shortcut = $this->adminElement($xpath, '//header[@data-shell-topbar]//kbd[@data-command-shortcut]');
        $this->assertSame('true', $shortcut->getAttribute('aria-hidden'));
        $this->assertStringContainsString('pointer-events-none', $shortcut->getAttribute('class'), 'O clique no atalho cai no link.');
        $this->assertStringContainsString('xl:inline-flex', $shortcut->getAttribute('class'));
    }

    public function test_visitors_get_no_palette(): void
    {
        $xpath = $this->page($this->get(route('blog.index')));

        $this->assertSame(0, $xpath->query('//dialog[@data-ui-command]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-command-open]')->length);
    }

    public function test_palette_script_uses_the_platform_shortcut_and_the_combobox_pattern(): void
    {
        $source = file_get_contents(resource_path('js/ui/command.js'));

        $this->assertStringContainsString('export function initCommandPalettes(root = document)', $source);
        $this->assertStringContainsString("import { closeDialog, openDialog } from './dialog';", $source);
        $this->assertStringContainsString("setAttribute('aria-activedescendant'", $source);
        $this->assertStringContainsString("setAttribute('aria-selected', 'true')", $source);
        $this->assertStringContainsString("event.key === 'ArrowDown'", $source);
        $this->assertStringContainsString("'Meta+K' : 'Control+K'", $source);
        $this->assertStringContainsString("document.querySelector('dialog[open]') === null", $source, 'Não abre por cima de outro diálogo.');
        $this->assertStringContainsString("dialog.addEventListener('ui:dialog-open'", $source);
        $this->assertStringNotContainsString('innerHTML', $source);
    }

    /**
     * @return list<string>
     */
    private function optionLabels(DOMXPath $xpath, DOMElement $palette, string $group): array
    {
        return array_map(
            fn (DOMElement $label): string => $this->adminText($label),
            $this->adminElements($xpath, './/*[@data-command-group="'.$group.'"]//*[@role="option"]//*[@data-command-label]', $palette),
        );
    }

    private function visibleName(DOMElement $element): string
    {
        $clone = $element->cloneNode(true);

        foreach (iterator_to_array($clone->getElementsByTagName('kbd')) as $hidden) {
            $hidden->parentNode->removeChild($hidden);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $clone->textContent));
    }

    private function page(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->assertOk()->getContent(), LIBXML_NOERROR);

        return new DOMXPath($document);
    }
}
