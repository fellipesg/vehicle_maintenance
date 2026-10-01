<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.stat>: rótulo e valor em lista de definição, número tabular e tendência que não depende
 * só da cor.
 */
class StatTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_label_and_value_are_a_definition_pair_with_tabular_numbers(): void
    {
        $xpath = $this->renderUi('<x-ui.stat label="Veículos" value="12" hint="3 com Selo da oficina" />');
        $stat = $this->uiElement($xpath, '//div[@data-slot="stat"]');

        $this->assertHasClasses(['rounded-card', 'border-border', 'bg-surface', 'p-4', 'shadow-sm'], $stat);
        $this->assertSame('Veículos', $this->uiText($this->uiElement($xpath, '//dl/dt')));

        $value = $this->uiElement($xpath, '//dl/dd');
        $this->assertSame('12', $this->uiText($value));
        $this->assertHasClasses(['tabular-nums', 'text-2xl', 'font-semibold', 'text-foreground'], $value);

        $hint = $this->uiElement($xpath, '//p[@data-slot="stat-hint"]');
        $this->assertSame('3 com Selo da oficina', $this->uiText($hint));
        $this->assertHasClasses(['text-xs', 'text-subtle-foreground'], $hint);
    }

    public function test_zero_is_a_value_but_missing_label_or_value_is_rejected(): void
    {
        $this->assertSame('0', $this->uiText($this->uiElement($this->renderUi('<x-ui.stat label="Pendências" :value="0" />'), '//dd')));

        $this->assertUiRejects('<x-ui.stat label="Pendências" />', 'x-ui.stat precisa de value.');
        $this->assertUiRejects('<x-ui.stat value="3" />', 'x-ui.stat precisa de label.');
    }

    public function test_href_turns_the_whole_card_into_an_interactive_link(): void
    {
        $xpath = $this->renderUi('<x-ui.stat label="Manutenções" value="48" href="/usuario/manutencoes" icon="wrench-screwdriver" />');
        $link = $this->uiElement($xpath, '//a[@data-slot="stat"]');

        $this->assertSame('/usuario/manutencoes', $link->getAttribute('href'));
        $this->assertHasClasses(['hover:shadow-md', 'motion-safe:hover:-translate-y-0.5', 'focus-visible:outline-ring', 'motion-reduce:transition-none'], $link);
        $this->assertSame('true', $this->uiElement($xpath, '//span[@data-slot="stat-icon"]/svg')->getAttribute('aria-hidden'));
    }

    public function test_trend_has_an_icon_color_and_screen_reader_direction(): void
    {
        $up = $this->renderUi('<x-ui.stat label="Veículos" value="12" trend="up" trend-label="+3 este mês" />');
        $upTrend = $this->uiElement($up, '//p[@data-slot="stat-trend"]');
        $this->assertHasClasses(['text-success', 'tabular-nums'], $upTrend);
        $this->assertSame('Aumento: +3 este mês', $this->uiText($upTrend));
        $this->assertSame('Aumento:', trim($this->uiElement($up, '//p[@data-slot="stat-trend"]//span[@class="sr-only"]')->textContent));
        $this->assertSame(1, $this->uiCount($up, '//p[@data-slot="stat-trend"]/svg[@aria-hidden="true"]'));

        $down = $this->uiElement($this->renderUi('<x-ui.stat label="OS abertas" value="2" trend="down" trend-label="-1 esta semana" />'), '//p[@data-slot="stat-trend"]');
        $this->assertHasClasses(['text-danger'], $down);
        $this->assertSame('Queda: -1 esta semana', $this->uiText($down));

        $costUp = $this->uiElement($this->renderUi('<x-ui.stat label="Gasto no ano" value="R$ 4.320,00" trend="up" trend-label="+12%" trend-tone="negative" />'), '//p[@data-slot="stat-trend"]');
        $this->assertHasClasses(['text-danger'], $costUp);

        $flat = $this->uiElement($this->renderUi('<x-ui.stat label="Veículos" value="12" trend="neutral" trend-label="Sem mudança" />'), '//p[@data-slot="stat-trend"]');
        $this->assertHasClasses(['text-muted-foreground'], $flat);
        $this->assertSame('Estável: Sem mudança', $this->uiText($flat));
    }

    public function test_trend_requires_a_label(): void
    {
        $this->assertUiRejects('<x-ui.stat label="Veículos" value="12" trend="up" />', 'x-ui.stat precisa de trend-label quando tem trend.');
        $this->assertUiRejects('<x-ui.stat label="Veículos" value="12" trend="sobe" trend-label="+1" />', 'x-ui.stat: trend "sobe" não existe.');
    }
}
