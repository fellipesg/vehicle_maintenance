<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.input-group>: complementos dentro da borda, com borda, foco e erro na moldura.
 */
class InputGroupTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_addons_wrap_the_control_and_the_field_name_reaches_it(): void
    {
        $document = $this->renderComponent(
            '<x-ui.field name="mileage" label="Quilometragem (km)"><x-ui.input-group class="max-w-xs"><x-slot:trailing>km</x-slot:trailing><x-ui.input inputmode="numeric" /></x-ui.input-group></x-ui.field>',
        );

        $group = $this->element($document, '[data-slot="input-group"]');
        $addon = $this->element($document, '[data-slot="input-group-addon"][data-align="trailing"]');
        $input = $this->element($document, 'input');

        $this->assertSame('km', trim($addon->textContent));
        $this->assertSame('mileage', $input->getAttribute('name'));
        $this->assertSame('mileage', $this->element($document, 'label')->getAttribute('for'));
        $this->assertContains('max-w-xs', $this->classesOf($group));
        $this->assertContains('text-muted-foreground', $this->classesOf($addon));
        $this->assertNull($document->querySelector('[data-align="leading"]'));
    }

    public function test_frame_carries_focus_error_and_disabled_states(): void
    {
        $classes = $this->classesOf($this->element($this->renderComponent('<x-ui.input-group><x-slot:leading>R$</x-slot:leading><x-ui.input name="price" /></x-ui.input-group>'), '[data-slot="input-group"]'));

        foreach ([
            'border-input',
            'rounded-control',
            'has-[[data-slot=control]:focus:not([aria-invalid=true])]:border-ring',
            'has-[[data-slot=control]:focus]:ring-2',
            'has-[[data-slot=control][aria-invalid=true]]:border-danger',
            'has-[[data-slot=control][aria-invalid=true]:focus]:ring-danger/60',
            'has-[[data-slot=control]:disabled]:bg-surface-muted',
            'motion-reduce:transition-none',
            '[&_[data-slot=control]]:pl-2',
        ] as $class) {
            $this->assertContains($class, $classes);
        }

        $this->assertNotContains('[&_input[data-slot=control]]:pr-2', $classes, 'Sem complemento à direita, o padding do campo fica.');
    }

    public function test_button_addon_sits_flush_against_the_border(): void
    {
        $document = $this->renderComponent('<x-ui.input-group><x-ui.input name="code" /><x-slot:trailing><button type="button" aria-label="Copiar código">C</button></x-slot:trailing></x-ui.input-group>');

        $this->assertContains('has-[>button]:pr-0', $this->classesOf($this->element($document, '[data-align="trailing"]')));
    }
}
