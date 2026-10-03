<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.skeleton>: formato do conteúdo final, anúncio de carregamento e pulso só com motion-safe.
 */
class SkeletonTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_default_line_announces_loading(): void
    {
        $xpath = $this->renderUi('<x-ui.skeleton />');
        $root = $this->uiElement($xpath, '//div[@data-slot="skeleton"]');

        $this->assertSame('line', $root->getAttribute('data-shape'));
        $this->assertSame('true', $root->getAttribute('aria-busy'));
        $this->assertFalse($root->hasAttribute('aria-hidden'));

        $status = $this->uiElement($xpath, '//span[@role="status"]');
        $this->assertSame('Carregando…', $this->uiText($status));
        $this->assertHasClasses(['sr-only'], $status);

        $bars = $xpath->query('//div[@aria-hidden="true"]/div');
        $this->assertSame(1, $bars->length);
        $this->assertHasClasses(['h-4', 'bg-surface-muted', 'motion-safe:animate-pulse', 'rounded-control'], $bars->item(0));
        $this->assertLacksClasses(['animate-pulse'], $bars->item(0));
    }

    public function test_multiple_lines_end_shorter(): void
    {
        $xpath = $this->renderUi('<x-ui.skeleton lines="3" label="Carregando manutenções…" />');
        $bars = $xpath->query('//div[@aria-hidden="true"]/div');

        $this->assertSame(3, $bars->length);
        $this->assertLacksClasses(['w-2/3'], $bars->item(0));
        $this->assertHasClasses(['w-2/3'], $bars->item(2));
        $this->assertSame('Carregando manutenções…', $this->uiText($this->uiElement($xpath, '//span[@role="status"]')));
    }

    public function test_empty_label_makes_it_decorative(): void
    {
        $xpath = $this->renderUi('<x-ui.skeleton shape="block" label="" />');
        $root = $this->uiElement($xpath, '//div[@data-slot="skeleton"]');

        $this->assertSame('true', $root->getAttribute('aria-hidden'));
        $this->assertFalse($root->hasAttribute('aria-busy'));
        $this->assertSame(0, $this->uiCount($xpath, '//*[@role="status"]'));
    }

    public function test_block_and_circle_have_default_sizes_that_a_class_replaces(): void
    {
        $block = $this->uiElement($this->renderUi('<x-ui.skeleton shape="block" />'), '//div[@data-slot="skeleton"]');
        $this->assertHasClasses(['h-24', 'w-full'], $block);

        $tallBlock = $this->uiElement($this->renderUi('<x-ui.skeleton shape="block" class="h-48 sm:h-64" />'), '//div[@data-slot="skeleton"]');
        $this->assertHasClasses(['h-48', 'sm:h-64', 'w-full'], $tallBlock);
        $this->assertLacksClasses(['h-24'], $tallBlock);

        $narrowBlock = $this->uiElement($this->renderUi('<x-ui.skeleton shape="block" class="h-10 w-40" />'), '//div[@data-slot="skeleton"]');
        $this->assertHasClasses(['h-10', 'w-40'], $narrowBlock);
        $this->assertLacksClasses(['w-full', 'h-24'], $narrowBlock);

        $circleXpath = $this->renderUi('<x-ui.skeleton shape="circle" />');
        $this->assertHasClasses(['size-10', 'shrink-0'], $this->uiElement($circleXpath, '//div[@data-slot="skeleton"]'));
        $this->assertHasClasses(['rounded-full', 'motion-safe:animate-pulse'], $this->uiElement($circleXpath, '//div[@data-slot="skeleton"]/div'));

        $smallCircle = $this->uiElement($this->renderUi('<x-ui.skeleton shape="circle" class="size-8" />'), '//div[@data-slot="skeleton"]');
        $this->assertLacksClasses(['size-10'], $smallCircle);
    }

    public function test_unknown_shape_is_rejected(): void
    {
        $this->assertUiRejects('<x-ui.skeleton shape="card" />', 'x-ui.skeleton: shape "card" não existe. Use line, block, circle.');
    }
}
