<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.fieldset>: legenda, descrição e erro do grupo, ligados ao <fieldset>.
 */
class FieldsetTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_legend_and_description_are_linked_to_the_group(): void
    {
        $document = $this->renderComponent(
            '<x-ui.fieldset legend="Dados do veículo" description="Confira com o documento do carro." id="dados-veiculo" class="mt-6"><x-ui.field name="plate" label="Placa"><x-ui.input /></x-ui.field></x-ui.fieldset>',
        );

        $fieldset = $this->element($document, 'fieldset');

        $this->assertSame('Dados do veículo', trim($this->element($document, 'fieldset > legend')->textContent));
        $this->assertSame('dados-veiculo', $fieldset->getAttribute('id'));
        $this->assertSame(['dados-veiculo-description'], $this->describedByOf($fieldset));
        $this->assertContains('mt-6', $this->classesOf($fieldset));
        $this->assertContains('min-w-0', $this->classesOf($fieldset));
        $this->assertNotNull($document->querySelector('fieldset [data-slot="fieldset-content"] input#plate'));
        $this->assertFalse($fieldset->hasAttribute('data-invalid'));
    }

    public function test_group_error_required_legend_and_inline_layout(): void
    {
        $this->withViewErrors(['has_warranty' => ['Diga se o serviço tem garantia.']]);

        $document = $this->renderComponent(
            '<x-ui.fieldset name="has_warranty" legend="Tem garantia?" required inline><x-ui.radio value="1" label="Sim" /><x-ui.radio value="0" label="Não" /></x-ui.fieldset>',
        );

        $fieldset = $this->element($document, 'fieldset');
        $legend = $this->element($document, 'legend');

        $this->assertSame('Tem garantia?* (obrigatório)', $legend->textContent);
        $this->assertSame('true', $fieldset->getAttribute('data-invalid'));
        $this->assertSame(['has_warranty-error'], $this->describedByOf($fieldset));
        $this->assertStringContainsString('Erro: Diga se o serviço tem garantia.', $this->element($document, '#has_warranty-error')->textContent);
        $this->assertContains('flex-wrap', $this->classesOf($this->element($document, '[data-slot="fieldset-content"]')));
        $this->assertSame('has_warranty', $this->element($document, '#has_warranty_1')->getAttribute('name'));
    }

    public function test_screen_reader_only_legend(): void
    {
        $this->assertContains('sr-only', $this->classesOf($this->element($this->renderComponent('<x-ui.fieldset legend="Filtros" legend-sr-only><x-ui.checkbox name="a" label="A" /></x-ui.fieldset>'), 'legend')));
    }
}
