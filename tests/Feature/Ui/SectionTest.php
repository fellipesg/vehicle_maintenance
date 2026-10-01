<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.section>: <section> rotulada pelo próprio h2, com descrição e ações.
 */
class SectionTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_section_is_labelled_by_its_heading(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.section title="Últimas manutenções" description="As 5 mais recentes dos seus veículos.">
                <x-slot:actions>
                    <x-ui.link href="/usuario/manutencoes" arrow>Ver todas</x-ui.link>
                </x-slot:actions>
                <p>Lista</p>
            </x-ui.section>
            BLADE);

        $section = $this->uiElement($xpath, '//section[@data-slot="section"]');
        $title = $this->uiElement($xpath, '//h2[@data-slot="section-title"]');

        $this->assertSame($title->getAttribute('id'), $section->getAttribute('aria-labelledby'));
        $this->assertMatchesRegularExpression('/^secao-[a-z0-9]{8}-titulo$/', $title->getAttribute('id'));
        $this->assertSame('Últimas manutenções', $this->uiText($title));
        $this->assertHasClasses(['text-lg', 'font-semibold', 'text-foreground'], $title);
        $this->assertHasClasses(['text-sm', 'text-muted-foreground'], $this->uiElement($xpath, '//p[@data-slot="section-description"]'));
        $this->assertSame('/usuario/manutencoes', $this->uiElement($xpath, '//div[@data-slot="section-actions"]/a')->getAttribute('href'));
        $this->assertSame('Lista', $this->uiText($this->uiElement($xpath, '//div[@data-slot="section-content"]')));
    }

    public function test_custom_id_and_heading_level(): void
    {
        $xpath = $this->renderUi('<x-ui.section id="historico" title="Histórico" heading-level="h3" class="mt-8">x</x-ui.section>');
        $section = $this->uiElement($xpath, '//section');

        $this->assertSame('historico', $section->getAttribute('id'));
        $this->assertSame('historico-titulo', $section->getAttribute('aria-labelledby'));
        $this->assertSame('historico-titulo', $this->uiElement($xpath, '//h3')->getAttribute('id'));
        $this->assertHasClasses(['mt-8', 'space-y-4'], $section);
        $this->assertSame(0, $this->uiCount($xpath, '//div[@data-slot="section-actions"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//p[@data-slot="section-description"]'));
    }

    public function test_title_is_required(): void
    {
        $this->assertUiRejects('<x-ui.section>x</x-ui.section>', 'x-ui.section precisa de title.');
    }
}
