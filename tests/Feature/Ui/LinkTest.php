<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.link>: cor --color-link (AA), sublinhado quando está no texto e aviso de nova aba.
 */
class LinkTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_standalone_link_uses_the_link_token_and_underlines_on_hover_and_focus(): void
    {
        $link = $this->uiElement($this->renderUi('<x-ui.link href="/usuario/veiculos">Ver todos</x-ui.link>'), '//a');

        $this->assertSame('/usuario/veiculos', $link->getAttribute('href'));
        $this->assertSame('link', $link->getAttribute('data-slot'));
        $this->assertSame('Ver todos', $this->uiText($link));
        $this->assertHasClasses(['text-link', 'hover:text-link-hover', 'hover:underline', 'focus-visible:underline', 'inline-flex', 'focus-visible:outline-ring'], $link);
        $this->assertLacksClasses(['underline'], $link);
        $this->assertFalse($link->hasAttribute('target'));
        $this->assertFalse($link->hasAttribute('download'));
    }

    public function test_inline_link_is_always_underlined(): void
    {
        $link = $this->uiElement($this->renderUi('<p>Leia os <x-ui.link href="/termos" variant="inline">termos de uso</x-ui.link>.</p>'), '//a');

        $this->assertHasClasses(['underline', 'text-link'], $link);
        $this->assertLacksClasses(['inline-flex'], $link);
    }

    public function test_inline_link_has_no_loose_whitespace_inside(): void
    {
        $html = (string) $this->blade('<x-ui.link href="/termos" variant="inline" external>termos</x-ui.link>');

        $this->assertMatchesRegularExpression('#<a [^>]+>termos<svg [^>]+>.*</svg><span class="sr-only"> \(abre em nova aba\)</span></a>#s', trim($html));
    }

    public function test_link_adds_no_whitespace_around_itself_in_running_text(): void
    {
        $html = trim((string) $this->blade('<p>Leia os (<x-ui.link href="/termos" variant="inline">termos</x-ui.link>).</p>'));

        $this->assertMatchesRegularExpression('#^<p>Leia os \(<a [^>]+>termos</a>\)\.</p>$#', $html);
    }

    public function test_external_link_opens_a_new_tab_safely_and_says_so(): void
    {
        $xpath = $this->renderUi('<x-ui.link href="https://www.gov.br/" external>Portal gov.br</x-ui.link>');
        $link = $this->uiElement($xpath, '//a');

        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertSame('noopener', $link->getAttribute('rel'));
        $this->assertSame('(abre em nova aba)', trim($this->uiElement($xpath, '//a/span[@class="sr-only"]')->textContent));
        $this->assertSame('Portal gov.br (abre em nova aba)', $this->uiText($link));

        $icon = $this->uiElement($xpath, '//a/svg');
        $this->assertSame('true', $icon->getAttribute('aria-hidden'));
    }

    public function test_leading_icon_and_moving_arrow(): void
    {
        $xpath = $this->renderUi('<x-ui.link href="/voltar" icon="arrow-left" arrow>Voltar para Meus veículos</x-ui.link>');
        $icons = $xpath->query('//a/svg');

        $this->assertSame(2, $icons->length);
        $this->assertHasClasses(['group'], $this->uiElement($xpath, '//a'));
        $this->assertHasClasses([
            'motion-safe:group-hover:translate-x-[3px]',
            'motion-safe:group-focus-visible:translate-x-[3px]',
            'motion-reduce:transition-none',
            'duration-base',
            'ease-smooth-out',
        ], $icons->item(1));
    }

    public function test_extra_classes_and_attributes_are_merged(): void
    {
        $link = $this->uiElement($this->renderUi('<x-ui.link href="/pdf" class="text-xs" aria-describedby="dica-pdf">Abrir PDF</x-ui.link>'), '//a');

        $this->assertHasClasses(['text-xs', 'text-link'], $link);
        $this->assertSame('dica-pdf', $link->getAttribute('aria-describedby'));
    }

    public function test_unknown_variant_is_rejected(): void
    {
        $this->assertUiRejects('<x-ui.link href="/" variant="back">Voltar</x-ui.link>', 'x-ui.link: variant "back" não existe. Use standalone, inline.');
        $this->assertUiRejects('<x-ui.link>Sem destino</x-ui.link>', 'x-ui.link precisa de href.');
    }
}
