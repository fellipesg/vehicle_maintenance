<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.segmented>: grupo de escolha única com alvo de 40px, em links (aria-current) ou em botões
 * de alternância (aria-pressed), com estado que não depende só da cor.
 */
class SegmentedTest extends TestCase
{
    use InspectsUiMarkup;

    /**
     * @var list<array<string, string>>
     */
    private const PROVENANCE_LINKS = [
        ['value' => '', 'label' => 'Todas', 'href' => '/admin/veiculos/7'],
        ['value' => '1', 'label' => 'Selo da oficina', 'href' => '/admin/veiculos/7?verified=1'],
        ['value' => '0', 'label' => 'Declaradas', 'href' => '/admin/veiculos/7?verified=0'],
    ];

    public function test_links_mode_marks_the_current_option_with_aria_current_and_a_check(): void
    {
        $xpath = $this->renderUi('<x-ui.segmented label="Filtrar por procedência" :options="$options" value="1" class="mb-4" />', ['options' => self::PROVENANCE_LINKS]);

        $nav = $this->uiElement($xpath, '//nav');
        $this->assertSame('Filtrar por procedência', $nav->getAttribute('aria-label'));
        $this->assertSame('segmented', $nav->getAttribute('data-slot'));
        $this->assertHasClasses(['inline-flex', 'rounded-control', 'border-border-strong', 'p-0.5', 'mb-4'], $nav);

        $links = $xpath->query('//nav/a');
        $this->assertSame(3, $links->length);
        $this->assertSame(['Todas', 'Selo da oficina', 'Declaradas'], array_map(fn ($link): string => $this->uiText($link), iterator_to_array($links)));

        $current = $this->uiElement($xpath, '//a[@aria-current="page"]');
        $this->assertSame('/admin/veiculos/7?verified=1', $current->getAttribute('href'));
        $this->assertHasClasses(['bg-accent', 'font-semibold', 'text-accent-foreground'], $current);
        $this->assertSame(1, $this->uiCount($xpath, '//a[@aria-current="page"]/svg[@aria-hidden="true"]'), 'Check na opção ativa, além da cor.');
        $this->assertSame(1, $this->uiCount($xpath, '//a[@aria-current]'));
        $this->assertSame(0, $this->uiCount($xpath, '//a[not(@aria-current)]/svg'));

        foreach ($links as $link) {
            $this->assertHasClasses(['min-h-10', 'focus-visible:outline-ring', 'motion-reduce:transition-none'], $link);
            $this->assertLacksClasses(['min-h-9'], $link);
        }
    }

    public function test_empty_value_selects_the_option_without_value(): void
    {
        $xpath = $this->renderUi('<x-ui.segmented label="Filtro" :options="$options" :value="null" />', ['options' => self::PROVENANCE_LINKS]);

        $this->assertSame('Todas', $this->uiText($this->uiElement($xpath, '//a[@aria-current="page"]')));
    }

    public function test_buttons_mode_uses_aria_pressed_and_keeps_extra_attributes_before_it(): void
    {
        $options = [
            ['value' => '', 'label' => 'Todas', 'attributes' => ['data-maintenance-filter' => '']],
            ['value' => '1', 'label' => 'Selo da oficina', 'attributes' => ['data-maintenance-filter' => '1']],
            ['value' => '0', 'label' => 'Declaradas', 'attributes' => ['data-maintenance-filter' => '0']],
        ];

        $html = (string) $this->blade('<x-ui.segmented mode="buttons" label="Filtrar por procedência" :options="$options" value="0" />', ['options' => $options]);
        $xpath = $this->parseHtml($html);

        $group = $this->uiElement($xpath, '//div[@role="group"]');
        $this->assertSame('Filtrar por procedência', $group->getAttribute('aria-label'));
        $this->assertSame(0, $this->uiCount($xpath, '//a'));

        $buttons = $xpath->query('//div[@role="group"]/button');
        $this->assertSame(3, $buttons->length);
        $this->assertSame(['false', 'false', 'true'], array_map(fn ($button): string => $button->getAttribute('aria-pressed'), iterator_to_array($buttons)));

        foreach ($buttons as $button) {
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertHasClasses(['group', 'min-h-10', 'aria-pressed:bg-accent', 'aria-pressed:font-semibold', 'aria-pressed:text-accent-foreground'], $button);
            $this->assertHasClasses(['hidden', 'group-aria-pressed:block'], $this->uiElement($xpath, './/svg', $button));
        }

        $this->assertMatchesRegularExpression('/data-maintenance-filter="1"[^>]*aria-pressed="false"/', $html, 'O JS lê o data-* e troca só o aria-pressed.');
    }

    public function test_option_icon_replaces_the_check_and_equal_shares_the_width(): void
    {
        $xpath = $this->renderUi('<x-ui.segmented label="Visualização" :options="$options" value="map" equal />', ['options' => [
            ['value' => 'list', 'label' => 'Lista', 'href' => '/lista', 'icon' => 'bars-3'],
            ['value' => 'map', 'label' => 'Mapa', 'href' => '/mapa', 'icon' => 'map'],
        ]]);

        $this->assertSame(2, $this->uiCount($xpath, '//nav/a/svg'), 'Cada opção mostra o próprio ícone, ativa ou não.');

        foreach ($xpath->query('//nav/a') as $link) {
            $this->assertHasClasses(['flex-1'], $link);
        }
    }

    public function test_extra_attributes_and_labels_are_escaped(): void
    {
        $html = (string) $this->blade('<x-ui.segmented mode="buttons" label="Filtro" :options="$options" />', ['options' => [
            ['value' => 'a', 'label' => '<b>Todas</b>', 'attributes' => ['data-x' => '"><script>']],
        ]]);

        $this->assertStringContainsString('&lt;b&gt;Todas&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_props_are_validated(): void
    {
        $this->assertUiRejects('<x-ui.segmented :options="[]" />', 'x-ui.segmented precisa de label');
        $this->assertUiRejects('<x-ui.segmented label="Filtro" mode="tabs" :options="[]" />', 'x-ui.segmented: mode "tabs" não existe');
        $this->assertUiRejects('<x-ui.segmented label="Filtro" :options="[[\'value\' => \'1\', \'label\' => \'Todas\']]" />', 'href na opção "Todas"');
        $this->assertUiRejects('<x-ui.segmented label="Filtro" mode="buttons" :options="[[\'value\' => \'1\']]" />', 'label em cada opção');
    }
}
