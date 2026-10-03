<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.form-section>: seção numerada dos formulários longos (Registrar manutenção, Nova OS, perfil
 * da oficina). Dentro do formulário é um <fieldset> com o h2 na <legend>; fora dele, uma <section>
 * rotulada pelo h2.
 */
class FormSectionTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_fieldset_names_the_group_with_the_numbered_heading_and_the_description(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.form-section id="secao-servico" :number="2" title="Serviço" description="O que foi feito e quando.">
                <x-slot:actions><button type="button">Adicionar peça</button></x-slot:actions>
                <p>Campos</p>
            </x-ui.form-section>
            BLADE);

        $fieldset = $this->uiElement($xpath, '//fieldset[@data-slot="form-section"]');
        $this->assertSame('secao-servico', $fieldset->getAttribute('id'));
        $this->assertSame('secao-servico-descricao', $fieldset->getAttribute('aria-describedby'));
        $this->assertFalse($fieldset->hasAttribute('aria-labelledby'), 'A legend já nomeia o fieldset.');
        $this->assertHasClasses(['rounded-card', 'border-border', 'bg-surface', 'scroll-mt-24'], $fieldset);

        $heading = $this->uiElement($xpath, '//fieldset/legend/h2');
        $this->assertSame('secao-servico-titulo', $heading->getAttribute('id'));
        $this->assertSame('Etapa 2: Serviço', trim((string) preg_replace('/^\d+\s*/', '', $this->uiText($heading))));
        $this->assertSame('2', $this->uiText($this->uiElement($xpath, '//h2/span[@aria-hidden="true"]')));
        $this->assertSame('O que foi feito e quando.', $this->uiText($this->uiElement($xpath, '//p[@id="secao-servico-descricao"]')));
        $this->assertSame('Adicionar peça', $this->uiText($this->uiElement($xpath, '//div[@data-slot="form-section-actions"]/button')));
        $this->assertSame('Campos', $this->uiText($this->uiElement($xpath, '//div[@data-slot="form-section-content"]')));
    }

    public function test_section_outside_a_form_is_labelled_by_its_heading_and_number_is_optional(): void
    {
        $xpath = $this->renderUi('<x-ui.form-section as="section" id="secao-veiculo" title="Veículo">x</x-ui.form-section>');

        $section = $this->uiElement($xpath, '//section[@data-slot="form-section"]');
        $this->assertSame('secao-veiculo-titulo', $section->getAttribute('aria-labelledby'));
        $this->assertFalse($section->hasAttribute('aria-describedby'));
        $this->assertSame(0, $this->uiCount($xpath, '//legend'));
        $this->assertSame(0, $this->uiCount($xpath, '//h2/span[@aria-hidden="true"]'));
        $this->assertSame('Veículo', $this->uiText($this->uiElement($xpath, '//h2')));
    }

    public function test_generated_id_when_none_is_given(): void
    {
        $xpath = $this->renderUi('<x-ui.form-section title="Contato">x</x-ui.form-section>');

        $this->assertMatchesRegularExpression('/^secao-[a-z0-9]{8}$/', $this->uiElement($xpath, '//fieldset')->getAttribute('id'));
    }

    public function test_title_is_required_and_as_is_validated(): void
    {
        $this->assertUiRejects('<x-ui.form-section>x</x-ui.form-section>', 'x-ui.form-section precisa de title.');
        $this->assertUiRejects('<x-ui.form-section title="X" as="div">x</x-ui.form-section>', 'x-ui.form-section');
    }
}
