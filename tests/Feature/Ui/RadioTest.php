<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.radio>: opção de escolha única, com name, selected e erro vindos do <x-ui.fieldset>.
 */
class RadioTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_radios_inherit_name_and_selected_from_the_fieldset(): void
    {
        $document = $this->renderComponent(
            '<x-ui.fieldset name="category" legend="Categoria do serviço" selected="brakes">'
            .'<x-ui.radio value="oil" label="Troca de óleo" />'
            .'<x-ui.radio value="brakes" label="Freios" description="Pastilhas, discos e fluido." />'
            .'</x-ui.fieldset>',
        );

        $oil = $this->element($document, '#category_oil');
        $brakes = $this->element($document, '#category_brakes');

        $this->assertSame('radio', $oil->getAttribute('type'));
        $this->assertSame('category', $oil->getAttribute('name'));
        $this->assertSame('category', $brakes->getAttribute('name'));
        $this->assertFalse($oil->hasAttribute('checked'));
        $this->assertTrue($brakes->hasAttribute('checked'));
        $this->assertSame(['category_brakes-description'], $this->describedByOf($brakes));
        $this->assertSame('category_oil', $this->element($document, 'label[for="category_oil"]')->getAttribute('for'));
        $this->assertContains('min-h-10', $this->classesOf($this->element($document, 'label[for="category_oil"]')));
        $this->assertContains('rounded-full', $this->classesOf($oil));
    }

    public function test_group_error_is_linked_from_every_option(): void
    {
        $this->withViewErrors(['category' => ['Escolha a categoria.']]);

        $document = $this->renderComponent(
            '<x-ui.fieldset name="category" legend="Categoria" required><x-ui.radio value="oil" label="Óleo" /><x-ui.radio value="tires" label="Pneus" /></x-ui.fieldset>',
        );

        foreach (['#category_oil', '#category_tires'] as $selector) {
            $radio = $this->element($document, $selector);

            $this->assertSame('true', $radio->getAttribute('aria-invalid'));
            $this->assertSame(['category-error'], $this->describedByOf($radio));
            $this->assertFalse($radio->hasAttribute('required'), 'O asterisco do grupo não liga validação do navegador.');
        }

        $this->assertStringContainsString('Escolha a categoria.', $this->element($document, '#category-error')->textContent);
    }

    public function test_old_input_and_checked_prop(): void
    {
        $this->assertTrue($this->element($this->renderComponent('<x-ui.radio name="fuel" value="flex" label="Flex" checked />'), 'input')->hasAttribute('checked'));

        $this->withOldInput(['fuel' => 'gas']);

        $document = $this->renderComponent('<x-ui.radio name="fuel" value="flex" label="Flex" checked /><x-ui.radio name="fuel" value="gas" label="Gasolina" />');

        $this->assertFalse($this->element($document, '#fuel_flex')->hasAttribute('checked'));
        $this->assertTrue($this->element($document, '#fuel_gas')->hasAttribute('checked'));
    }
}
