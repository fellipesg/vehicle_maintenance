<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.switch>: checkbox nativo com role="switch" e aria-checked, valor de desligado e rótulo
 * clicável.
 */
class SwitchTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_switch_is_a_native_checkbox_with_switch_role_and_checked_state(): void
    {
        $document = $this->renderComponent('<x-ui.switch name="is_active" label="Modelo ativo" checked />');

        $input = $this->element($document, 'input[type="checkbox"]');
        $label = $this->element($document, 'label');

        $this->assertSame('switch', $input->getAttribute('role'));
        $this->assertSame('true', $input->getAttribute('aria-checked'));
        $this->assertTrue($input->hasAttribute('checked'));
        $this->assertSame('is_active', $input->getAttribute('id'));
        $this->assertSame('is_active', $label->getAttribute('for'));
        $this->assertContains('sr-only', $this->classesOf($input));
        $this->assertContains('peer', $this->classesOf($input));
        $this->assertContains('min-h-10', $this->classesOf($label));
        $this->assertStringContainsString('Modelo ativo', $label->textContent);
    }

    public function test_off_state_sends_zero_through_a_hidden_input(): void
    {
        $document = $this->renderComponent('<x-ui.switch name="notify_email" label="Receber avisos por e-mail" />');

        $input = $this->element($document, 'input[type="checkbox"]');
        $hidden = $this->element($document, 'input[type="hidden"]');

        $this->assertSame('false', $input->getAttribute('aria-checked'));
        $this->assertFalse($input->hasAttribute('checked'));
        $this->assertSame('notify_email', $hidden->getAttribute('name'));
        $this->assertSame('0', $hidden->getAttribute('value'));
        $this->assertNull($this->renderComponent('<x-ui.switch name="x" label="X" :unchecked-value="false" />')->querySelector('input[type="hidden"]'));
    }

    public function test_track_and_thumb_show_state_focus_and_reduce_motion(): void
    {
        $document = $this->renderComponent('<x-ui.switch name="a" label="A" />');

        $track = $this->element($document, 'label > span');
        $thumb = $this->element($document, 'input + span[aria-hidden="true"]');

        foreach (['h-6', 'w-11', 'bg-input', 'has-checked:bg-accent-foreground', 'has-focus-visible:outline-2', 'has-focus-visible:outline-ring', 'motion-reduce:transition-none', 'duration-fast'] as $class) {
            $this->assertContains($class, $this->classesOf($track));
        }

        foreach (['peer-checked:translate-x-5.5', 'motion-reduce:transition-none', 'ease-smooth-out'] as $class) {
            $this->assertContains($class, $this->classesOf($thumb));
        }
    }

    public function test_old_input_description_and_error(): void
    {
        $this->withOldInput(['is_active' => '0']);
        $this->withViewErrors(['is_active' => ['Não dá para desativar o modelo em uso.']]);

        $document = $this->renderComponent('<x-ui.switch name="is_active" label="Ativo" description="Modelos inativos não aparecem na OS." checked />');

        $input = $this->element($document, 'input[type="checkbox"]');

        $this->assertFalse($input->hasAttribute('checked'));
        $this->assertSame('false', $input->getAttribute('aria-checked'));
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(['is_active-description', 'is_active-error'], $this->describedByOf($input));
        $this->assertStringContainsString('Erro: Não dá para desativar o modelo em uso.', $this->element($document, '#is_active-error')->textContent);
    }
}
