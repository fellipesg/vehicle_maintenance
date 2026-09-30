<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.segmented> em formulário GET (name): os botões de alternância (aria-pressed) enviam o
 * filtro sem JS; contagem por opção e aria-controls.
 */
class SegmentedSubmitTest extends TestCase
{
    use InspectsUiMarkup;

    /**
     * @var list<array<string, int|string>>
     */
    private const OPTIONS = [
        ['value' => '', 'label' => 'Todas', 'count' => 12],
        ['value' => '1', 'label' => 'Selo da oficina', 'count' => 1234],
        ['value' => '0', 'label' => 'Declaradas', 'count' => 0],
    ];

    public function test_named_buttons_submit_the_value_and_keep_aria_pressed(): void
    {
        $xpath = $this->renderUi('<form method="get"><x-ui.segmented mode="buttons" name="verified" controls="historico-lista" label="Filtrar por procedência" :options="$options" value="1" /></form>', ['options' => self::OPTIONS]);

        $buttons = $xpath->query('//div[@role="group"]/button');
        $this->assertSame(3, $buttons->length);

        foreach ($buttons as $button) {
            $this->assertSame('submit', $button->getAttribute('type'));
            $this->assertSame('verified', $button->getAttribute('name'));
            $this->assertSame('historico-lista', $button->getAttribute('aria-controls'));
        }

        $this->assertSame(['', '1', '0'], array_map(fn ($button): string => $button->getAttribute('value'), iterator_to_array($buttons)));
        $this->assertSame(['false', 'true', 'false'], array_map(fn ($button): string => $button->getAttribute('aria-pressed'), iterator_to_array($buttons)));
    }

    public function test_counts_render_after_the_label_in_tabular_numbers(): void
    {
        $xpath = $this->renderUi('<x-ui.segmented mode="buttons" name="verified" label="Filtro" :options="$options" />', ['options' => self::OPTIONS]);

        $counts = $xpath->query('//span[@data-slot="segmented-count"]');
        $this->assertSame(['12', '1.234', '0'], array_map(fn ($count): string => $this->uiText($count), iterator_to_array($counts)));
        $this->assertHasClasses(['tabular-nums', 'text-xs'], $counts->item(0));
        $this->assertSame('Selo da oficina 1.234', $this->uiText($this->uiElement($xpath, '//button[@value="1"]')));
    }

    public function test_without_name_buttons_stay_plain_and_links_ignore_name_and_controls(): void
    {
        $plain = $this->renderUi('<x-ui.segmented mode="buttons" label="Filtro" :options="$options" />', ['options' => self::OPTIONS]);
        $this->assertSame('button', $this->uiElement($plain, '//button[1]')->getAttribute('type'));
        $this->assertFalse($this->uiElement($plain, '//button[1]')->hasAttribute('name'));
        $this->assertFalse($this->uiElement($plain, '//button[1]')->hasAttribute('aria-controls'));

        $links = $this->renderUi('<x-ui.segmented label="Filtro" name="verified" controls="x" :options="$options" />', ['options' => [
            ['value' => '', 'label' => 'Todas', 'href' => '/a', 'count' => 3],
        ]]);
        $this->assertFalse($this->uiElement($links, '//a')->hasAttribute('name'));
        $this->assertFalse($this->uiElement($links, '//a')->hasAttribute('aria-controls'));
        $this->assertSame('3', $this->uiText($this->uiElement($links, '//a/span[@data-slot="segmented-count"]')));
    }
}
