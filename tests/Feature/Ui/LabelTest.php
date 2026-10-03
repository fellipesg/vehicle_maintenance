<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.label>: rótulo com marcação de obrigatório e opcional.
 */
class LabelTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_label_points_to_the_control_and_marks_required(): void
    {
        $label = $this->element($this->renderComponent('<x-ui.label for="plate" required class="mb-1">Placa</x-ui.label>'), 'label');

        $this->assertSame('plate', $label->getAttribute('for'));
        $this->assertSame('Placa* (obrigatório)', $label->textContent);
        $this->assertSame('true', $label->querySelector('.text-danger')->getAttribute('aria-hidden'));
        $this->assertContains('text-foreground', $this->classesOf($label));
        $this->assertContains('mb-1', $this->classesOf($label));
    }

    public function test_optional_and_screen_reader_only(): void
    {
        $optional = $this->element($this->renderComponent('<x-ui.label for="nick" optional>Apelido</x-ui.label>'), 'label');
        $hidden = $this->element($this->renderComponent('<x-ui.label for="q" sr-only>Buscar</x-ui.label>'), 'label');
        $bare = $this->element($this->renderComponent('<x-ui.label>Solto</x-ui.label>'), 'label');

        $this->assertSame('Apelido (opcional)', $optional->textContent);
        $this->assertContains('sr-only', $this->classesOf($hidden));
        $this->assertFalse($bare->hasAttribute('for'));
    }
}
