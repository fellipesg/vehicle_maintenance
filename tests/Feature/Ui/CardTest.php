<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.card>: superfície pelos tokens, cabeçalho com ação e card clicável por stretched link.
 */
class CardTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_plain_card_uses_the_surface_tokens_and_default_padding(): void
    {
        $card = $this->uiElement($this->renderUi('<x-ui.card>Conteúdo</x-ui.card>'), '//div[@data-slot="card"]');

        $this->assertHasClasses(['rounded-card', 'border', 'border-border', 'bg-surface', 'shadow-sm', 'p-4', 'sm:p-6'], $card);
        $this->assertLacksClasses(['hover:shadow-md', 'motion-safe:hover:-translate-y-0.5'], $card, 'Card estático não levanta no hover.');
        $this->assertSame('Conteúdo', $this->uiText($card));
    }

    public function test_padding_options(): void
    {
        $expectations = ['none' => [], 'sm' => ['p-4'], 'lg' => ['p-6', 'sm:p-8']];

        foreach ($expectations as $padding => $classes) {
            $card = $this->uiElement($this->renderUi('<x-ui.card :padding="$padding">x</x-ui.card>', ['padding' => $padding]), '//div[@data-slot="card"]');

            $this->assertHasClasses($classes, $card);

            if ($padding === 'none') {
                $this->assertLacksClasses(['p-4', 'p-6', 'sm:p-6'], $card);
            }
        }
    }

    public function test_header_with_title_description_and_action(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.card title="Últimas manutenções" description="As 5 mais recentes.">
                <x-slot:action>
                    <a href="/manutencoes">Ver todas</a>
                </x-slot:action>
                Lista
            </x-ui.card>
            BLADE);

        $title = $this->uiElement($xpath, '//h3[@data-slot="card-title"]');
        $this->assertSame('Últimas manutenções', $this->uiText($title));
        $this->assertHasClasses(['text-base', 'font-semibold', 'text-foreground'], $title);
        $this->assertSame('As 5 mais recentes.', $this->uiText($this->uiElement($xpath, '//p[@data-slot="card-description"]')));
        $this->assertHasClasses(['text-muted-foreground'], $this->uiElement($xpath, '//p[@data-slot="card-description"]'));
        $this->assertHasClasses(['grid-cols-[minmax(0,1fr)_auto]'], $this->uiElement($xpath, '//div[@data-slot="card-header"]'));
        $this->assertSame('Ver todas', $this->uiText($this->uiElement($xpath, '//div[@data-slot="card-action"]')));
        $this->assertSame('Lista', $this->uiText($this->uiElement($xpath, '//div[@data-slot="card-content"]')));
    }

    public function test_heading_level_and_sectioning_element_are_configurable(): void
    {
        $xpath = $this->renderUi('<x-ui.card as="section" title="Dados da loja" heading-level="h2">x</x-ui.card>');
        $card = $this->uiElement($xpath, '//section[@data-slot="card"]');
        $title = $this->uiElement($xpath, '//h2[@data-slot="card-title"]');

        $this->assertNotSame('', $title->getAttribute('id'));
        $this->assertSame($title->getAttribute('id'), $card->getAttribute('aria-labelledby'));

        $div = $this->uiElement($this->renderUi('<x-ui.card title="Resumo">x</x-ui.card>'), '//div[@data-slot="card"]');
        $this->assertFalse($div->hasAttribute('aria-labelledby'), 'div não é região: não precisa de nome.');

        $this->assertUiRejects('<x-ui.card title="x" heading-level="h1">x</x-ui.card>', 'x-ui.card: heading-level "h1" não existe.');
    }

    public function test_clickable_card_has_a_single_stretched_link_named_by_the_title(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.card as="article" href="/usuario/veiculos/7" title="Gol 1.0 2020" description="Placa ABC1D23">
                <x-slot:action>
                    <x-ui.icon-button icon="ellipsis-horizontal" label="Mais ações" size="sm" />
                </x-slot:action>
                Última revisão em 12/03/2026
                <x-slot:footer class="border-t border-border pt-4">
                    <x-ui.button variant="secondary" size="sm">Exportar PDF</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
            BLADE);

        $card = $this->uiElement($xpath, '//article[@data-slot="card"]');
        $this->assertHasClasses(['relative', 'hover:shadow-md', 'hover:border-accent-border', 'motion-safe:hover:-translate-y-0.5', 'motion-reduce:transition-none', 'duration-fast'], $card);

        $links = $xpath->query('//a');
        $this->assertSame(1, $links->length, 'O card inteiro tem um link só.');

        $link = $links->item(0);
        $this->assertSame('/usuario/veiculos/7', $link->getAttribute('href'));
        $this->assertSame('Gol 1.0 2020', $this->uiText($link));
        $this->assertSame('h3', $link->parentNode->nodeName);
        $this->assertHasClasses(['after:absolute', 'after:inset-0', 'focus-visible:outline-hidden', 'focus-visible:after:outline-2', 'focus-visible:after:outline-ring'], $link);

        $this->assertHasClasses(['relative', 'z-10'], $this->uiElement($xpath, '//div[@data-slot="card-action"]'));
        $footer = $this->uiElement($xpath, '//div[@data-slot="card-footer"]');
        $this->assertHasClasses(['relative', 'z-10', 'border-t', 'pt-4', 'flex'], $footer);
    }

    public function test_clickable_card_without_title_needs_a_label(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.card href="/usuario/veiculos/7" label="Abrir Gol 1.0 2020">
                <x-slot:header>Cabeçalho próprio</x-slot:header>
                Corpo
            </x-ui.card>
            BLADE);

        $link = $this->uiElement($xpath, '//a[@data-slot="card-link"]');
        $this->assertSame('Abrir Gol 1.0 2020', $this->uiText($link));
        $this->assertHasClasses(['absolute', 'inset-0'], $link);
        $this->assertSame('Cabeçalho próprio', $this->uiText($this->uiElement($xpath, '//div[@data-slot="card-header"]')));

        $this->assertUiRejects('<x-ui.card href="/x">Corpo</x-ui.card>', 'x-ui.card precisa de title ou label quando tem href.');
    }

    public function test_extra_attributes_and_classes_are_merged(): void
    {
        $card = $this->uiElement($this->renderUi('<x-ui.card as="li" class="h-full" id="veiculo-7" data-vehicle="7">x</x-ui.card>'), '//li[@data-slot="card"]');

        $this->assertHasClasses(['h-full', 'bg-surface'], $card);
        $this->assertSame('veiculo-7', $card->getAttribute('id'));
        $this->assertSame('7', $card->getAttribute('data-vehicle'));
    }
}
