<?php

namespace Tests\Feature\Ui;

use DOMXPath;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.tabs>, <x-ui.tab> e <x-ui.tab-panel>: padrão WAI-ARIA Tabs com tabindex móvel, ligado por
 * resources/js/ui/tabs.js.
 */
class TabsTest extends TestCase
{
    use InspectsUiMarkup;

    private const TEMPLATE = <<<'BLADE'
        <x-ui.tabs label="Seções do veículo" :variant="$variant" :sync-url="$syncUrl">
            <x-slot:tabs>
                <x-ui.tab target="linha-do-tempo" :active="true" icon="clock">Linha do tempo</x-ui.tab>
                <x-ui.tab target="placas" badge="2" badge-label="placas antigas">Histórico de placas</x-ui.tab>
                <x-ui.tab target="documentos" disabled>Documentos</x-ui.tab>
            </x-slot:tabs>
            <x-ui.tab-panel id="linha-do-tempo" :active="true">Eventos</x-ui.tab-panel>
            <x-ui.tab-panel id="placas">Placas</x-ui.tab-panel>
            <x-ui.tab-panel id="documentos">Documentos</x-ui.tab-panel>
        </x-ui.tabs>
        BLADE;

    public function test_tablist_is_named_and_kept_away_from_the_preline_tabs_plugin(): void
    {
        $xpath = $this->render();
        $tablist = $this->uiElement($xpath, '//div[@role="tablist"]');

        $this->assertSame('Seções do veículo', $tablist->getAttribute('aria-label'));
        $this->assertSame('horizontal', $tablist->getAttribute('aria-orientation'));
        $this->assertHasClasses(['--prevent-on-load-init', 'bg-surface-muted', 'rounded-control', 'p-1', 'overflow-x-auto'], $tablist);
        $this->assertTrue($this->uiElement($xpath, '//div[@data-ui-tabs]')->hasAttribute('data-ui-tabs'));
        $this->assertFalse($this->uiElement($xpath, '//div[@data-ui-tabs]')->hasAttribute('data-ui-tabs-sync-url'));
    }

    public function test_tabs_link_to_panels_and_only_the_active_tab_is_in_the_tab_order(): void
    {
        $xpath = $this->render();
        $active = $this->uiElement($xpath, '//button[@id="linha-do-tempo-aba"]');
        $inactive = $this->uiElement($xpath, '//button[@id="placas-aba"]');

        $this->assertSame('tab', $active->getAttribute('role'));
        $this->assertSame('button', $active->getAttribute('type'));
        $this->assertSame('linha-do-tempo', $active->getAttribute('aria-controls'));
        $this->assertSame('true', $active->getAttribute('aria-selected'));
        $this->assertSame('0', $active->getAttribute('tabindex'));
        $this->assertSame('linha-do-tempo', $active->getAttribute('data-ui-tab'));
        $this->assertSame('false', $inactive->getAttribute('aria-selected'));
        $this->assertSame('-1', $inactive->getAttribute('tabindex'));
        $this->assertSame(3, $this->uiCount($xpath, '//div[@role="tablist"]/button[@role="tab"]'));
    }

    public function test_panels_are_labelled_by_their_tab_focusable_and_hidden_unless_active(): void
    {
        $xpath = $this->render();
        $visible = $this->uiElement($xpath, '//div[@id="linha-do-tempo"]');
        $hidden = $this->uiElement($xpath, '//div[@id="placas"]');

        $this->assertSame('tabpanel', $visible->getAttribute('role'));
        $this->assertSame('linha-do-tempo-aba', $visible->getAttribute('aria-labelledby'));
        $this->assertSame('0', $visible->getAttribute('tabindex'));
        $this->assertFalse($visible->hasAttribute('hidden'));
        $this->assertTrue($hidden->hasAttribute('hidden'));
        $this->assertHasClasses(['motion-safe:transition-opacity', 'motion-safe:starting:opacity-0', 'motion-reduce:transition-none'], $visible);
    }

    public function test_active_tab_is_marked_by_shape_and_weight_not_only_color(): void
    {
        $pills = $this->uiElement($this->render(), '//button[@id="placas-aba"]');
        $this->assertHasClasses(['min-h-10', 'aria-selected:bg-surface', 'aria-selected:shadow-sm', 'aria-selected:font-semibold', 'text-muted-foreground', 'focus-visible:outline-offset-[-2px]'], $pills);

        $lineXpath = $this->render(variant: 'line');
        $line = $this->uiElement($lineXpath, '//button[@id="placas-aba"]');
        $this->assertHasClasses(['min-h-11', 'border-b-2', 'aria-selected:border-link', 'aria-selected:font-semibold'], $line);
        $this->assertLacksClasses(['aria-selected:bg-surface'], $line);
        $this->assertHasClasses(['border-b', 'border-border'], $this->uiElement($lineXpath, '//div[@role="tablist"]'));
    }

    public function test_badge_icon_and_disabled_tab(): void
    {
        $xpath = $this->render();

        $this->assertSame('2 placas antigas', $this->uiText($this->uiElement($xpath, '//button[@id="placas-aba"]/span[2]')));
        $this->assertSame('placas antigas', $this->uiText($this->uiElement($xpath, '//button[@id="placas-aba"]//span[@class="sr-only"]')));
        $this->assertSame('true', $this->uiElement($xpath, '//button[@id="linha-do-tempo-aba"]/svg')->getAttribute('aria-hidden'));
        $disabled = $this->uiElement($xpath, '//button[@id="documentos-aba"]');
        $this->assertTrue($disabled->hasAttribute('disabled'));
        $this->assertSame('false', $disabled->getAttribute('aria-selected'));
    }

    public function test_root_keeps_clear_of_the_sticky_topbar_when_opened_from_a_fragment(): void
    {
        // tabs.js rola até a raiz das abas quando a URL traz o fragmento de uma aba (#documentos);
        // o scroll-margin deixa o tablist abaixo da navbar de 64px (e da topbar de 56px do admin).
        $this->assertHasClasses(['min-w-0', 'scroll-mt-20'], $this->uiElement($this->render(syncUrl: true), '//div[@data-ui-tabs]'));
    }

    public function test_sync_url_flag_and_variant_validation(): void
    {
        $this->assertTrue($this->uiElement($this->render(syncUrl: true), '//div[@data-ui-tabs]')->hasAttribute('data-ui-tabs-sync-url'));
        $this->assertUiRejects('<x-ui.tabs label="Abas" variant="cards"><x-slot:tabs></x-slot:tabs></x-ui.tabs>', 'x-ui.tabs: variant "cards" não existe');
        $this->assertUiRejects('<x-ui.tabs label=""><x-slot:tabs></x-slot:tabs></x-ui.tabs>', 'x-ui.tabs precisa de label');
    }

    public function test_tabs_script_manages_selection_roving_tabindex_and_the_url(): void
    {
        $script = file_get_contents(resource_path('js/ui/tabs.js'));

        foreach (['ArrowRight:', 'ArrowLeft:', 'Home:', 'End:', "'aria-selected'", 'tab.tabIndex = isSelected ? 0 : -1;', 'panel.hidden = !isSelected;', 'history.replaceState', "'ui:tab-change'"] as $fragment) {
            $this->assertStringContainsString($fragment, $script);
        }
    }

    private function render(string $variant = 'pills', bool $syncUrl = false): DOMXPath
    {
        return $this->renderUi(self::TEMPLATE, ['variant' => $variant, 'syncUrl' => $syncUrl]);
    }
}
