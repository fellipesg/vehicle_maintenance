<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.table>: região rolável nomeada, legenda, scope nos cabeçalhos e estado vazio com colspan.
 */
class TableTest extends TestCase
{
    use InspectsUiMarkup;

    private const TABLE = <<<'BLADE'
        <x-ui.table caption="Oficinas cadastradas" empty="Nenhuma oficina cadastrada" empty-description="As oficinas aparecem aqui depois do cadastro." empty-icon="building-storefront">
            <x-slot:head>
                <tr>
                    <th>Nome</th>
                    <th>Cidade</th>
                    <th scope="colgroup" class="text-right">OS</th>
                    <th><span class="sr-only">Ações</span></th>
                </tr>
            </x-slot:head>
            @foreach($workshops as $workshop)
                <tr>
                    <th scope="row" class="font-medium">{{ $workshop['name'] }}</th>
                    <td>{{ $workshop['city'] }}</td>
                    <td class="text-right">{{ $workshop['count'] }}</td>
                    <td></td>
                </tr>
            @endforeach
        </x-ui.table>
        BLADE;

    public function test_scrollable_region_is_named_focusable_and_captioned(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['workshops' => [['name' => 'Oficina do Zé', 'city' => 'Recife', 'count' => 12]]]);

        $region = $this->uiElement($xpath, '//div[@data-slot="table"]');
        $this->assertSame('region', $region->getAttribute('role'));
        $this->assertSame('Oficinas cadastradas', $region->getAttribute('aria-label'));
        $this->assertSame('0', $region->getAttribute('tabindex'));
        $this->assertHasClasses(['overflow-x-auto', 'rounded-card', 'border-border', 'bg-surface'], $region);

        $caption = $this->uiElement($xpath, '//table/caption');
        $this->assertSame('Oficinas cadastradas', $this->uiText($caption));
        $this->assertHasClasses(['sr-only'], $caption);

        $table = $this->uiElement($xpath, '//table');
        $this->assertHasClasses(['w-full', 'text-left', 'tabular-nums', '[:where(&)_:is(th,td)]:px-4', '[:where(&)_:is(th,td)]:py-3'], $table);
    }

    public function test_header_cells_get_column_scope_unless_they_already_have_one(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['workshops' => [['name' => 'Oficina do Zé', 'city' => 'Recife', 'count' => 12]]]);
        $headers = $xpath->query('//thead//th');

        $this->assertSame(4, $headers->length);
        $this->assertSame('col', $headers->item(0)->getAttribute('scope'));
        $this->assertSame('col', $headers->item(1)->getAttribute('scope'));
        $this->assertSame('colgroup', $headers->item(2)->getAttribute('scope'), 'scope escrito na view é mantido.');
        $this->assertHasClasses(['text-right'], $headers->item(2));
        $this->assertSame('col', $headers->item(3)->getAttribute('scope'));
        $this->assertSame('row', $this->uiElement($xpath, '//tbody//th')->getAttribute('scope'), 'Cabeçalho de linha no corpo não é tocado.');

        $thead = $this->uiElement($xpath, '//thead');
        $this->assertHasClasses(['bg-surface-muted/60', 'text-muted-foreground', 'text-xs', 'uppercase'], $thead);
    }

    public function test_rows_render_and_hide_the_empty_state(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['workshops' => [
            ['name' => 'Oficina do Zé', 'city' => 'Recife', 'count' => 12],
            ['name' => 'Auto Center', 'city' => 'Olinda', 'count' => 3],
        ]]);

        $this->assertSame(2, $this->uiCount($xpath, '//tbody/tr'));
        $this->assertSame(0, $this->uiCount($xpath, '//tr[@data-slot="table-empty"]'));
        $this->assertHasClasses(['divide-y', 'divide-border', '[&>tr:not([data-slot=table-empty]):hover]:bg-surface-muted/50', 'motion-reduce:[&>tr]:transition-none'], $this->uiElement($xpath, '//tbody'));
    }

    public function test_empty_body_shows_the_empty_state_across_all_columns(): void
    {
        $xpath = $this->renderUi(self::TABLE, ['workshops' => []]);

        $cell = $this->uiElement($xpath, '//tr[@data-slot="table-empty"]/td');
        $this->assertSame('4', $cell->getAttribute('colspan'));

        $empty = $this->uiElement($xpath, '//tr[@data-slot="table-empty"]//div[@data-slot="empty-state"]');
        $this->assertLacksClasses(['border-dashed'], $empty);
        $this->assertSame('Nenhuma oficina cadastrada', $this->uiText($this->uiElement($xpath, '//p[@data-slot="empty-state-title"]')));
        $this->assertSame('As oficinas aparecem aqui depois do cadastro.', $this->uiText($this->uiElement($xpath, '//p[@data-slot="empty-state-description"]')));
        $this->assertSame(1, $this->uiCount($xpath, '//span[@data-slot="empty-state-icon"]'));
    }

    public function test_empty_slot_columns_override_and_foot(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.table caption="Itens da OS" label="Itens da ordem de serviço" :columns="5" show-caption>
                <x-slot:head><tr><th>Item</th><th>Total</th></tr></x-slot:head>
                <x-slot:empty>
                    <x-ui.empty-state title="Nenhum item" heading-level="p" variant="plain">
                        <x-slot:actions><x-ui.button size="sm">Adicionar item</x-ui.button></x-slot:actions>
                    </x-ui.empty-state>
                </x-slot:empty>
                <x-slot:body></x-slot:body>
                <x-slot:foot><tr><th scope="row">Total</th><td>R$ 0,00</td></tr></x-slot:foot>
            </x-ui.table>
            BLADE);

        $this->assertSame('Itens da ordem de serviço', $this->uiElement($xpath, '//div[@data-slot="table"]')->getAttribute('aria-label'));
        $this->assertLacksClasses(['sr-only'], $this->uiElement($xpath, '//caption'));
        $this->assertSame('5', $this->uiElement($xpath, '//tr[@data-slot="table-empty"]/td')->getAttribute('colspan'));
        $this->assertSame('Adicionar item', $this->uiText($this->uiElement($xpath, '//tr[@data-slot="table-empty"]//button')));
        $this->assertSame('R$ 0,00', $this->uiText($this->uiElement($xpath, '//tfoot/tr/td')));
        $this->assertSame('row', $this->uiElement($xpath, '//tfoot/tr/th')->getAttribute('scope'));
    }

    public function test_without_empty_message_an_empty_body_stays_empty(): void
    {
        $xpath = $this->renderUi('<x-ui.table caption="Placas anteriores"><x-slot:head><tr><th>Placa</th></tr></x-slot:head></x-ui.table>');

        $this->assertSame(0, $this->uiCount($xpath, '//tbody/tr'));
    }

    public function test_caption_is_required(): void
    {
        $this->assertUiRejects('<x-ui.table><tr><td>x</td></tr></x-ui.table>', 'x-ui.table precisa de caption.');
    }
}
