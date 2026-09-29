<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Sidebar do admin recolhível (a partir de md): botão de alternância com nome fixo e aria-pressed,
 * estado em data-sidebar no <html> aplicado antes da pintura a partir do localStorage (com
 * try/catch), largura animada só com movimento permitido e rótulos que continuam para leitor de
 * tela quando só os ícones aparecem.
 */
class AdminSidebarCollapseTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_toggle_is_a_fixed_name_toggle_button_for_the_sidebar(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $toggle = $this->adminElement($xpath, '//header[@data-admin-topbar]//button[@data-sidebar-toggle]');
        $this->assertSame('Recolher menu lateral', $toggle->getAttribute('aria-label'));
        $this->assertSame('false', $toggle->getAttribute('aria-pressed'));
        $this->assertSame('nav-admin', $toggle->getAttribute('aria-controls'));
        $this->assertSame('nav-admin', $toggle->getAttribute('data-sidebar-toggle'));
        $this->assertSame('revisalog:admin-sidebar', $toggle->getAttribute('data-sidebar-storage-key'));

        $classes = explode(' ', $toggle->getAttribute('class'));
        foreach (['hidden', 'md:inline-flex', 'size-10', 'focus-visible:outline-ring', 'motion-reduce:transition-none'] as $class) {
            $this->assertContains($class, $classes, "Faltou {$class}: abaixo de md a sidebar é a gaveta, com o próprio botão.");
        }

        $icon = $this->adminElement($xpath, './/*[local-name()="svg"]', $toggle);
        $this->assertStringContainsString('in-data-[sidebar=collapsed]:rotate-180', $icon->getAttribute('class'));
        $this->assertStringContainsString('motion-reduce:transition-none', $icon->getAttribute('class'));
    }

    public function test_saved_state_is_applied_before_the_first_paint_and_survives_blocked_storage(): void
    {
        $html = $this->actingAs($this->adminUser())->get(route('admin.dashboard'))->assertOk()->getContent();

        $head = substr($html, 0, (int) strpos($html, '</head>'));
        $scriptStart = strpos($head, "window.localStorage.getItem('revisalog:admin-sidebar')");

        $this->assertNotFalse($scriptStart, 'O <head> lê a escolha salva.');
        $this->assertStringContainsString("document.documentElement.dataset.sidebar = 'collapsed';", $head);
        $this->assertMatchesRegularExpression('/try \{\s*if \(window\.localStorage/', $head, 'Acesso ao localStorage dentro de try/catch.');
        $this->assertStringContainsString('} catch (error) {', $head);
        $this->assertLessThan(strpos($head, 'build/assets') ?: strpos($head, '@vite') ?: PHP_INT_MAX, $scriptStart, 'Roda antes do CSS e do JS do app.');
    }

    public function test_collapsed_sidebar_keeps_icons_and_hides_labels_only_visually(): void
    {
        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.dashboard')));

        $sidebarClasses = explode(' ', $this->adminElement($xpath, '//aside[@id="nav-admin"]')->getAttribute('class'));
        foreach (['md:w-60', 'md:in-data-[sidebar=collapsed]:w-16', 'md:transition-[width]', 'md:duration-base', 'md:ease-linear', 'motion-reduce:transition-none'] as $class) {
            $this->assertContains($class, $sidebarClasses, "Faltou {$class} na sidebar.");
        }

        $nav = $this->adminElement($xpath, '//aside[@id="nav-admin"]//nav[@aria-label="Administração"]');
        $navClasses = explode(' ', $nav->getAttribute('class'));
        foreach (['md:in-data-[sidebar=collapsed]:[&_a>span]:sr-only', 'md:in-data-[sidebar=collapsed]:[&_li>p]:sr-only', 'md:in-data-[sidebar=collapsed]:[&_a]:justify-center'] as $class) {
            $this->assertContains($class, $navClasses, "Faltou {$class} no menu.");
        }
        $this->assertTrue($nav->hasAttribute('data-sidebar-nav'));
        $this->assertNotContains('hidden', $navClasses, 'Recolhido, o rótulo sai só da tela: continua como nome do link.');

        $brand = $this->adminElement($xpath, '//aside[@id="nav-admin"]//a[@href="'.route('admin.dashboard').'"]/span');
        $this->assertStringContainsString('md:in-data-[sidebar=collapsed]:sr-only', $brand->getAttribute('class'));

        $account = $this->adminElement($xpath, '//*[@data-admin-account]//button[@aria-haspopup="menu"]');
        $this->assertStringContainsString('md:in-data-[sidebar=collapsed]:justify-center', $account->getAttribute('class'));
        $this->assertStringContainsString('md:in-data-[sidebar=collapsed]:sr-only', $this->adminElement($xpath, './span[contains(@class, "flex-1")]', $account)->getAttribute('class'));
    }

    public function test_sidebar_script_persists_with_try_catch_and_shows_hover_and_focus_hints(): void
    {
        $source = file_get_contents(resource_path('js/ui/sidebar.js'));

        $this->assertStringContainsString('export function initSidebarToggles(root = document)', $source);
        $this->assertStringContainsString("dataset.sidebarToggleReady === 'true'", $source);
        $this->assertStringContainsString("setAttribute('aria-pressed'", $source);
        $this->assertStringNotContainsString("setAttribute('aria-label'", $source, 'Botão de alternância com nome fixo.');
        $this->assertSame(3, substr_count($source, '} catch {'), 'Leitura, gravação e o próprio acesso ao localStorage ficam em try/catch.');
        $this->assertStringContainsString("event.key === 'Escape'", $source, 'A dica some com Esc (WCAG 1.4.13).');
        $this->assertStringContainsString("matches(':focus-visible')", $source);
        $this->assertStringContainsString("setAttribute('aria-hidden', 'true')", $source, 'A dica é só visual: o nome é o do link.');
    }
}
