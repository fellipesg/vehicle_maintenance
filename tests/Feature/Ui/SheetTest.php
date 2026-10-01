<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.sheet>: painel lateral com o HSOverlay do Preline, nomeado pelo título e com fundo, Esc e
 * foco preso pelo plugin.
 */
class SheetTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_sheet_is_a_hidden_modal_hs_overlay_named_by_its_title(): void
    {
        $xpath = $this->renderUi('<x-ui.sheet id="filtros" title="Filtros" description="Refine a lista.">Campos</x-ui.sheet>');
        $sheet = $this->uiElement($xpath, '//div[@id="filtros"]');

        $this->assertSame('dialog', $sheet->getAttribute('role'));
        $this->assertSame('true', $sheet->getAttribute('aria-modal'));
        $this->assertSame('filtros-titulo', $sheet->getAttribute('aria-labelledby'));
        $this->assertSame('filtros-descricao', $sheet->getAttribute('aria-describedby'));
        $this->assertSame('-1', $sheet->getAttribute('tabindex'));
        $this->assertTrue($sheet->hasAttribute('data-ui-sheet'));
        $this->assertFalse($sheet->hasAttribute('data-close-from'));
        $this->assertHasClasses(['hs-overlay', 'hidden', 'fixed', 'z-[60]', 'h-dvh', 'bg-surface', 'text-foreground', 'theme-default', 'hs-overlay-open:translate-x-0', 'duration-slow', 'ease-smooth-out', 'motion-reduce:transition-none'], $sheet);
        $this->assertSame('Filtros', $this->uiText($this->uiElement($xpath, '//h2[@id="filtros-titulo"]')));
        $this->assertSame('Refine a lista.', $this->uiText($this->uiElement($xpath, '//p[@id="filtros-descricao"]')));
        $this->assertStringContainsString('Campos', $sheet->textContent);
    }

    public function test_backdrop_options_are_valid_json_with_the_overlay_token(): void
    {
        $sheet = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" />'), '//div[@id="s"]');
        $options = json_decode($sheet->getAttribute('data-hs-overlay-options'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('hs-overlay-backdrop', $options['backdropClasses']);
        $this->assertStringContainsString('bg-overlay', $options['backdropClasses']);
        $this->assertStringContainsString('motion-reduce:transition-none', $options['backdropClasses']);
    }

    public function test_close_button_toggles_the_same_overlay_and_is_a_44px_target(): void
    {
        $close = $this->uiElement($this->renderUi('<x-ui.sheet id="menu" title="Menu" />'), '//button[@aria-label="Fechar"]');

        $this->assertSame('#menu', $close->getAttribute('data-hs-overlay'));
        $this->assertSame('button', $close->getAttribute('type'));
        $this->assertHasClasses(['size-11'], $close);
    }

    public function test_side_and_size_are_mapped_to_literal_classes(): void
    {
        $right = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" />'), '//div[@id="s"]');
        $this->assertHasClasses(['end-0', 'translate-x-full', 'border-s', 'w-[min(24rem,calc(100vw-3rem))]'], $right);

        $left = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" side="left" size="sm" />'), '//div[@id="s"]');
        $this->assertHasClasses(['start-0', '-translate-x-full', 'border-e', 'w-[min(18rem,calc(100vw-3rem))]'], $left);
        $this->assertLacksClasses(['end-0', 'translate-x-full'], $left);

        $large = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" size="lg" />'), '//div[@id="s"]');
        $this->assertHasClasses(['w-[min(32rem,calc(100vw-3rem))]'], $large);

        $this->assertUiRejects('<x-ui.sheet id="s" title="T" side="bottom" />', 'x-ui.sheet: side "bottom" não existe');
        $this->assertUiRejects('<x-ui.sheet id="s" title="T" size="xl" />', 'x-ui.sheet: size "xl" não existe');
    }

    public function test_inverse_uses_the_dark_brand_scope(): void
    {
        $sheet = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" inverse />'), '//div[@id="s"]');

        $this->assertHasClasses(['theme-inverse'], $sheet);
        $this->assertLacksClasses(['theme-default'], $sheet);
    }

    public function test_close_from_becomes_a_data_attribute_for_the_matchmedia_script(): void
    {
        $sheet = $this->uiElement($this->renderUi('<x-ui.sheet id="s" title="T" close-from="lg" />'), '//div[@id="s"]');

        $this->assertSame('lg', $sheet->getAttribute('data-close-from'));
        $this->assertUiRejects('<x-ui.sheet id="s" title="T" close-from="2xl" />', 'x-ui.sheet: closeFrom "2xl" não existe');
    }

    public function test_footer_slot_is_pinned_below_the_scrolling_body(): void
    {
        $xpath = $this->renderUi('<x-ui.sheet id="s" title="T">Corpo<x-slot:footer><button type="submit">Aplicar filtros</button></x-slot:footer></x-ui.sheet>');
        $body = $this->uiElement($xpath, '//div[contains(concat(" ", normalize-space(@class), " "), " overflow-y-auto ")]');
        $footer = $this->uiElement($xpath, '//div[button[.="Aplicar filtros"]]');

        $this->assertHasClasses(['min-h-0', 'flex-1'], $body);
        $this->assertHasClasses(['shrink-0', 'border-t', 'border-border'], $footer);
        $this->assertSame(0, $this->uiCount($this->renderUi('<x-ui.sheet id="s" title="T" />'), '//div[contains(@class, "border-t")]'));
    }

    public function test_title_is_required(): void
    {
        $this->assertUiRejects('<x-ui.sheet id="s" title="" />', 'x-ui.sheet precisa de title');
    }
}
