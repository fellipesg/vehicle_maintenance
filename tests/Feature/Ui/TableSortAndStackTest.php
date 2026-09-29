<?php

namespace Tests\Feature\Ui;

use Illuminate\Http\Request;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.table>: ordenação por coluna com aria-sort e modo empilhado no celular com o rótulo da
 * coluna em cada célula (DS-22, GAR-34).
 */
class TableSortAndStackTest extends TestCase
{
    use InspectsUiMarkup;

    private const TABLE = <<<'BLADE'
        <x-ui.table caption="Manutenções" :sort="$sort" :direction="$direction" stack>
            <x-slot:head>
                <tr>
                    <th data-sort="data" data-sort-default="desc">Data</th>
                    <th data-sort="servico">Serviço</th>
                    <th class="text-right">Km</th>
                    <th><span class="sr-only">Ações</span></th>
                </tr>
            </x-slot:head>
            <tr>
                <td>12/03/2026</td>
                <th scope="row">Troca de óleo</th>
                <td class="text-right">45.000 km</td>
                <td><a href="/editar">Editar</a></td>
            </tr>
            <x-slot:foot><tr><th scope="row" colspan="3">Total</th><td>R$ 10,00</td></tr></x-slot:foot>
        </x-ui.table>
        BLADE;

    public function test_sorted_column_gets_aria_sort_and_every_sortable_header_links_to_the_next_direction(): void
    {
        $this->app->instance('request', Request::create('/manutencoes', 'GET', ['page' => '2', 'veiculo' => '3']));

        $xpath = $this->renderUi(self::TABLE, ['sort' => 'data', 'direction' => 'desc']);

        $dateHeader = $this->uiElement($xpath, '//thead//th[@data-sort="data"]');
        $this->assertSame('descending', $dateHeader->getAttribute('aria-sort'));
        $this->assertSame('col', $dateHeader->getAttribute('scope'));
        $this->assertFalse($this->uiElement($xpath, '//thead//th[@data-sort="servico"]')->hasAttribute('aria-sort'), 'Só a coluna ordenada leva aria-sort.');

        $dateLink = $this->uiElement($xpath, '//th[@data-sort="data"]/a[@data-slot="table-sort"]');
        $this->assertStringContainsString('ordenar=data', $dateLink->getAttribute('href'));
        $this->assertStringContainsString('direcao=asc', $dateLink->getAttribute('href'));
        $this->assertStringNotContainsString('page=', $dateLink->getAttribute('href'));
        $this->assertStringContainsString('veiculo=3', $dateLink->getAttribute('href'));
        $this->assertSame(1, $xpath->query('.//svg[@aria-hidden="true"]', $dateLink)->length, 'Seta na coluna ordenada.');
        $this->assertStringContainsString('ordenar em ordem crescente', $this->uiText($dateLink));

        $serviceLink = $this->uiElement($xpath, '//th[@data-sort="servico"]/a');
        $this->assertStringContainsString('direcao=asc', $serviceLink->getAttribute('href'));
        $this->assertSame(0, $xpath->query('.//svg', $serviceLink)->length);
        $this->assertSame(0, $this->uiCount($xpath, '//th[not(@data-sort)]/a'), 'Coluna sem data-sort não vira link.');
    }

    public function test_first_click_on_an_unsorted_column_uses_its_default_direction(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['sort' => 'servico', 'direction' => 'asc']);

        $this->assertStringContainsString('direcao=desc', $this->uiElement($xpath, '//th[@data-sort="data"]/a')->getAttribute('href'));
        $this->assertStringContainsString('direcao=desc', $this->uiElement($xpath, '//th[@data-sort="servico"]/a')->getAttribute('href'));
        $this->assertSame('ascending', $this->uiElement($xpath, '//th[@data-sort="servico"]')->getAttribute('aria-sort'));
    }

    public function test_custom_sort_url(): void
    {
        $xpath = $this->renderUi('<x-ui.table caption="T" sort="a" :sort-url="$url"><x-slot:head><tr><th data-sort="a">A</th></tr></x-slot:head><tr><td>1</td></tr></x-ui.table>', [
            'url' => fn (string $column, string $direction): string => "/lista/{$column}/{$direction}",
        ]);

        $this->assertSame('/lista/a/desc', $this->uiElement($xpath, '//th/a')->getAttribute('href'));
    }

    public function test_stack_mode_labels_cells_and_sets_explicit_roles(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['sort' => null, 'direction' => 'asc']);

        $region = $this->uiElement($xpath, '//div[@data-slot="table"]');
        $this->assertSame('md', $region->getAttribute('data-stack'));

        $table = $this->uiElement($xpath, '//table');
        $this->assertSame('table', $table->getAttribute('role'));
        $this->assertHasClasses(['max-md:block', 'max-md:[&>tbody>tr]:flex', 'max-md:[&>tbody>tr]:flex-col', 'max-md:[&>tbody>tr>th]:order-first'], $table);
        $this->assertStringContainsString('max-md:[&>tbody>tr>td[data-label]]:before:content-[attr(data-label)]', $table->getAttribute('class'));
        $this->assertHasClasses(['max-md:sr-only'], $this->uiElement($xpath, '//thead'));
        $this->assertSame('rowgroup', $this->uiElement($xpath, '//tbody')->getAttribute('role'));

        $cells = $xpath->query('//tbody/tr/*');
        $this->assertSame('row', $this->uiElement($xpath, '//tbody/tr')->getAttribute('role'));
        $this->assertSame('Data', $cells->item(0)->getAttribute('data-label'));
        $this->assertSame('cell', $cells->item(0)->getAttribute('role'));
        $this->assertSame('rowheader', $cells->item(1)->getAttribute('role'));
        $this->assertFalse($cells->item(1)->hasAttribute('data-label'), 'O título da linha não leva rótulo.');
        $this->assertSame('Km', $cells->item(2)->getAttribute('data-label'));
        $this->assertFalse($cells->item(3)->hasAttribute('data-label'), 'Coluna só com texto sr-only fica sem rótulo visível.');
        $this->assertSame('columnheader', $this->uiElement($xpath, '//thead//th[1]')->getAttribute('role'));

        $this->assertSame('rowgroup', $this->uiElement($xpath, '//tfoot')->getAttribute('role'));
        $this->assertSame('cell', $this->uiElement($xpath, '//tfoot//td')->getAttribute('role'));
    }

    public function test_without_stack_nothing_changes_and_colspan_counts_for_the_empty_row(): void
    {
        $xpath = $this->renderUi('<x-ui.table caption="T" empty="Nada"><x-slot:head><tr><th colspan="2">A</th><th>B</th></tr></x-slot:head></x-ui.table>');

        $this->assertFalse($this->uiElement($xpath, '//table')->hasAttribute('role'));
        $this->assertFalse($this->uiElement($xpath, '//div[@data-slot="table"]')->hasAttribute('data-stack'));
        $this->assertSame('3', $this->uiElement($xpath, '//tr[@data-slot="table-empty"]/td')->getAttribute('colspan'));
    }

    public function test_direction_is_validated(): void
    {
        $this->assertUiRejects('<x-ui.table caption="T" direction="up"><tr><td>1</td></tr></x-ui.table>', 'x-ui.table: direction "up" não existe');
    }
}
