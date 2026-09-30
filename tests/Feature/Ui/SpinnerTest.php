<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.spinner>: decorativo por padrão, status com rótulo, e gira só com motion-safe.
 */
class SpinnerTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_without_label_it_is_decorative(): void
    {
        $xpath = $this->renderUi('<x-ui.spinner />');
        $root = $this->uiElement($xpath, '//span[@data-slot="spinner"]');

        $this->assertSame('true', $root->getAttribute('aria-hidden'));
        $this->assertFalse($root->hasAttribute('role'));
        $this->assertSame(0, $this->uiCount($xpath, '//span[@class="sr-only"]'));

        $svg = $this->uiElement($xpath, '//svg');
        $this->assertHasClasses(['size-5', 'motion-safe:animate-spin'], $svg);
        $this->assertLacksClasses(['animate-spin'], $svg);
        $this->assertSame('currentColor', $this->uiElement($xpath, '//svg/circle')->getAttribute('stroke'));
    }

    public function test_label_turns_it_into_a_status(): void
    {
        $xpath = $this->renderUi('<x-ui.spinner label="Carregando veículos…" class="text-muted-foreground" />');
        $root = $this->uiElement($xpath, '//span[@data-slot="spinner"]');

        $this->assertSame('status', $root->getAttribute('role'));
        $this->assertFalse($root->hasAttribute('aria-hidden'));
        $this->assertHasClasses(['text-muted-foreground'], $root);
        $this->assertSame('Carregando veículos…', $this->uiText($this->uiElement($xpath, '//span[@class="sr-only"]')));
    }

    public function test_sizes(): void
    {
        $this->assertHasClasses(['size-4'], $this->uiElement($this->renderUi('<x-ui.spinner size="sm" />'), '//svg'));
        $this->assertHasClasses(['size-8'], $this->uiElement($this->renderUi('<x-ui.spinner size="lg" />'), '//svg'));
        $this->assertUiRejects('<x-ui.spinner size="4" />', 'x-ui.spinner: size "4" não existe.');
    }
}
