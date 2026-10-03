<?php

namespace Tests\Feature\Ui;

use Dom\Element;
use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.select>: opções (mapa, lista, grupos ou slot), valor escolhido e old(), placeholder e seta
 * própria.
 */
class SelectTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_options_map_with_selected_value_placeholder_and_own_arrow(): void
    {
        $document = $this->renderComponent(
            '<x-ui.select name="brand_id" :options="$brands" :value="2" placeholder="Selecione a marca" class="sm:max-w-xs" />',
            ['brands' => collect([1 => 'Fiat', 2 => 'Ford'])],
        );

        $select = $this->element($document, 'select');
        $options = iterator_to_array($select->querySelectorAll('option'));

        $this->assertSame('brand_id', $select->getAttribute('name'));
        $this->assertSame('brand_id', $select->getAttribute('id'));
        $this->assertSame(['', '1', '2'], array_map(fn (Element $option): string => $option->getAttribute('value'), $options));
        $this->assertSame('Selecione a marca', trim($options[0]->textContent));
        $this->assertFalse($options[0]->hasAttribute('disabled'), 'Sem required, a opção vazia continua escolhível.');
        $this->assertTrue($options[2]->hasAttribute('selected'));
        $this->assertFalse($options[0]->hasAttribute('selected'));
        $this->assertContains('appearance-none', $this->classesOf($select));
        $this->assertContains('bg-none', $this->classesOf($select));
        $this->assertContains('form-select', $this->classesOf($select));
        $this->assertSame('true', $this->element($document, '[data-slot="select"] > svg')->getAttribute('aria-hidden'));
        $this->assertContains('sm:max-w-xs', $this->classesOf($this->element($document, '[data-slot="select"]')), 'class vai para a moldura.');
    }

    public function test_required_placeholder_is_selected_but_cannot_be_chosen_again(): void
    {
        $placeholder = $this->element($this->renderComponent('<x-ui.select name="state" :options="[\'SP\' => \'São Paulo\']" placeholder="Selecione" required />'), 'option[value=""]');

        $this->assertTrue($placeholder->hasAttribute('disabled'));
        $this->assertTrue($placeholder->hasAttribute('selected'));
    }

    public function test_item_lists_groups_and_disabled_options(): void
    {
        $document = $this->renderComponent('<x-ui.select name="city" :options="$options" />', ['options' => [
            'Sudeste' => ['rj' => 'Rio de Janeiro', 'sp' => 'São Paulo'],
            ['value' => 'x', 'label' => 'Fora da área', 'disabled' => true],
        ]]);

        $group = $this->element($document, 'optgroup');

        $this->assertSame('Sudeste', $group->getAttribute('label'));
        $this->assertCount(2, $group->querySelectorAll('option'));
        $this->assertTrue($this->element($document, 'option[value="x"]')->hasAttribute('disabled'));
    }

    public function test_old_input_wins_and_multiple_accepts_a_list(): void
    {
        $this->withOldInput(['fuel' => 'flex', 'tags' => ['a', 'c']]);

        $document = $this->renderComponent(
            '<x-ui.select name="fuel" :options="[\'gas\' => \'Gasolina\', \'flex\' => \'Flex\']" value="gas" />'
            .'<x-ui.select name="tags[]" multiple :options="[\'a\' => \'A\', \'b\' => \'B\', \'c\' => \'C\']" :value="[\'b\']" />',
        );

        $this->assertTrue($this->element($document, '#fuel option[value="flex"]')->hasAttribute('selected'));
        $this->assertFalse($this->element($document, '#fuel option[value="gas"]')->hasAttribute('selected'));

        $multiple = $this->element($document, '#tags');

        $this->assertSame('tags[]', $multiple->getAttribute('name'));
        $this->assertTrue($multiple->querySelector('option[value="a"]')->hasAttribute('selected'));
        $this->assertFalse($multiple->querySelector('option[value="b"]')->hasAttribute('selected'));
        $this->assertTrue($multiple->querySelector('option[value="c"]')->hasAttribute('selected'));
        $this->assertNull($document->querySelector('#tags + svg'), 'Lista aberta não tem seta.');
        $this->assertNotContains('appearance-none', $this->classesOf($multiple));
    }

    public function test_slot_options_when_no_options_prop_and_invalid_state(): void
    {
        $this->withViewErrors(['kind' => ['Escolha o tipo.']]);

        $document = $this->renderComponent('<x-ui.select name="kind"><option value="car">Carro</option><option value="bike">Moto</option></x-ui.select>');

        $select = $this->element($document, 'select');

        $this->assertCount(2, $select->querySelectorAll('option'));
        $this->assertSame('true', $select->getAttribute('aria-invalid'));
        $this->assertContains('aria-invalid:border-danger', $this->classesOf($select));
    }

    public function test_option_labels_are_escaped(): void
    {
        $html = (string) $this->blade('<x-ui.select name="x" :options="[\'a\' => \'<b>Negrito</b>\']" />');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Negrito&lt;/b&gt;', $html);
    }
}
