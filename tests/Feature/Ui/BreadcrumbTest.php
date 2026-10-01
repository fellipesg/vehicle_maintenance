<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.breadcrumb>: nav "Trilha" com lista ordenada, página atual com aria-current e, no celular,
 * só o ancestral com link mais próximo (ou a página atual, quando nenhum tem link).
 */
class BreadcrumbTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_trail_structure_and_current_page(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['Início', '/usuario'],
            ['Meus veículos', '/usuario/veiculos'],
            ['Gol 1.0 2020', '/usuario/veiculos/7'],
        ]]);

        $nav = $this->uiElement($xpath, '//nav');
        $this->assertSame('Trilha', $nav->getAttribute('aria-label'));
        $this->assertSame('breadcrumb', $nav->getAttribute('data-slot'));
        $this->assertSame(3, $this->uiCount($xpath, '//nav/ol/li'));

        $links = $xpath->query('//nav//a');
        $this->assertSame(2, $links->length, 'A página atual não é link, mesmo com URL.');
        $this->assertSame('/usuario', $links->item(0)->getAttribute('href'));
        $this->assertSame('Meus veículos', $this->uiText($links->item(1)));

        $current = $this->uiElement($xpath, '//*[@aria-current="page"]');
        $this->assertSame('span', $current->tagName);
        $this->assertSame('Gol 1.0 2020', $this->uiText($current));
        $this->assertHasClasses(['text-foreground', 'font-medium'], $current);

        $separators = $xpath->query('//nav//svg[contains(@class, "max-sm:hidden")]');
        $this->assertSame(2, $separators->length, 'Um separador entre cada par de itens.');

        foreach ($xpath->query('//nav//svg') as $svg) {
            $this->assertSame('true', $svg->getAttribute('aria-hidden'));
        }
    }

    public function test_only_the_parent_shows_on_small_screens(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['Início', '/usuario'],
            ['Meus veículos', '/usuario/veiculos'],
            ['Gol 1.0 2020'],
        ]]);
        $items = $xpath->query('//nav/ol/li');

        $this->assertHasClasses(['max-sm:hidden'], $items->item(0));
        $this->assertLacksClasses(['max-sm:hidden'], $items->item(1));
        $this->assertHasClasses(['max-sm:hidden'], $items->item(2));

        $parentLink = $this->uiElement($xpath, '//nav/ol/li[2]/a');
        $this->assertHasClasses(['max-sm:min-h-10'], $parentLink);
        $this->assertSame(1, $this->uiCount($xpath, '//nav/ol/li[2]/a/svg[contains(@class, "sm:hidden")]'), 'Chevron de volta só no celular.');
        $this->assertSame(0, $this->uiCount($xpath, '//nav/ol/li[1]/a/svg'));
    }

    public function test_group_without_link_is_never_the_mobile_item(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="$items" />', ['items' => [['Frota'], ['Manutenções']]]);
        $items = $xpath->query('//nav/ol/li');

        $this->assertHasClasses(['max-sm:hidden'], $items->item(0));
        $this->assertSame('Frota', $this->uiText($items->item(0)));
        $this->assertLacksClasses(['max-sm:hidden'], $items->item(1));
        $this->assertSame('Manutenções', $this->uiText($this->uiElement($xpath, '//nav/ol/li[2]/span[@aria-current="page"]')));
        $this->assertSame(0, $this->uiCount($xpath, '//nav//a'));
    }

    public function test_mobile_item_is_the_nearest_ancestor_with_a_link(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['Frota'],
            ['Veículos', '/admin/veiculos'],
            ['Cadastro'],
            ['Gol 1.0 2020'],
        ]]);
        $items = $xpath->query('//nav/ol/li');

        $this->assertHasClasses(['max-sm:hidden'], $items->item(0));
        $this->assertLacksClasses(['max-sm:hidden'], $items->item(1));
        $this->assertHasClasses(['max-sm:hidden'], $items->item(2));
        $this->assertHasClasses(['max-sm:hidden'], $items->item(3));
        $this->assertSame(1, $this->uiCount($xpath, '//nav/ol/li[2]/a/svg[contains(@class, "sm:hidden")]'), 'O ancestral com link ganha o chevron de volta.');
    }

    public function test_associative_items_plain_strings_and_blank_labels(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['label' => 'Admin', 'href' => '/admin'],
            ['label' => '  '],
            'Categorias',
            ['label' => 'Nova categoria', 'url' => '/admin/blog/categorias/nova'],
        ]]);

        $this->assertSame(3, $this->uiCount($xpath, '//nav/ol/li'));
        $this->assertSame('/admin', $this->uiElement($xpath, '//nav//a')->getAttribute('href'));
        $this->assertSame(0, $this->uiCount($xpath, '//nav/ol/li[2]/a'), 'Item sem URL sai como texto.');
        $this->assertSame('Nova categoria', $this->uiText($this->uiElement($xpath, '//*[@aria-current="page"]')));
    }

    public function test_single_item_is_visible_everywhere_and_empty_list_renders_nothing(): void
    {
        $xpath = $this->renderUi('<x-ui.breadcrumb :items="[[\'Início\']]" />');

        $this->assertLacksClasses(['max-sm:hidden'], $this->uiElement($xpath, '//nav/ol/li'));
        $this->assertSame('', trim((string) $this->blade('<x-ui.breadcrumb :items="[]" />')));
    }

    public function test_labels_are_escaped(): void
    {
        $html = (string) $this->blade('<x-ui.breadcrumb :items="$items" />', ['items' => [['<b>Início</b>', '/'], ['Atual']]]);

        $this->assertStringContainsString('&lt;b&gt;Início&lt;/b&gt;', $html);
    }
}
