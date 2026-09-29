<?php

namespace Tests\Feature\Components;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleTimelineBuilder;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-vehicle-timeline>: renderizada no servidor, com as colunas como abas (ui/tabs.js) e um painel
 * de detalhes por evento (USR-18, USR-30).
 */
class VehicleTimelineComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_columns_are_tabs_with_one_prerendered_panel_each(): void
    {
        [$vehicle, $sealed, $declared] = $this->vehicleWithHistory();
        $timeline = app(VehicleTimelineBuilder::class)->build($vehicle);

        $xpath = $this->renderUi('<x-vehicle-timeline :timeline="$timeline" maintenance-url="/usuario/manutencoes/{id}" />', ['timeline' => $timeline]);

        $tablist = $this->uiElement($xpath, '//div[@role="tablist"]');
        $this->assertSame('Eventos da linha do tempo, do mais antigo ao mais recente', $tablist->getAttribute('aria-label'));
        $this->assertStringContainsString('--prevent-on-load-init', $tablist->getAttribute('class'));
        $this->assertSame(1, $this->uiCount($xpath, '//section[@data-vehicle-timeline]//div[@data-ui-tabs]'));

        $tabs = $xpath->query('//button[@role="tab"]');
        $panels = $xpath->query('//div[@role="tabpanel"]');
        $this->assertSame($tabs->length, $panels->length);
        $this->assertSame(count($timeline['events']), $tabs->length);

        foreach ($tabs as $tab) {
            $panel = $this->uiElement($xpath, '//div[@id="'.$tab->getAttribute('aria-controls').'"]');
            $this->assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
            $this->assertSame($tab->getAttribute('aria-controls'), $tab->getAttribute('data-ui-tab'));
            $this->assertSame($tab->getAttribute('aria-selected') === 'true', ! $panel->hasAttribute('hidden'));
            $this->assertSame($tab->getAttribute('aria-selected') === 'true' ? '0' : '-1', $tab->getAttribute('tabindex'));
        }

        $this->assertSame(1, $this->uiCount($xpath, '//button[@role="tab" and @aria-selected="true"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//script'), 'O JS não monta HTML: os painéis já vêm do servidor.');
        $this->assertSame(1, $this->uiCount($xpath, '//a[@href="/usuario/manutencoes/'.$sealed->id.'" and normalize-space()="Ver manutenção completa"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//a[@href="/usuario/manutencoes/'.$declared->id.'"]'));
    }

    public function test_columns_are_oldest_first_and_say_the_provenance_in_text(): void
    {
        [$vehicle] = $this->vehicleWithHistory();
        $timeline = app(VehicleTimelineBuilder::class)->build($vehicle);

        $xpath = $this->renderUi('<x-vehicle-timeline :timeline="$timeline" />', ['timeline' => $timeline]);

        $kilometers = array_map(
            fn (DOMElement $column): string => $this->uiText($xpath->query('./span[1]', $column)->item(0)),
            iterator_to_array($xpath->query('//button[@data-event-type="maintenance"]')),
        );
        $this->assertSame(['20.000 km', '30.000 km'], $kilometers);

        $this->assertSame(['Selo da oficina', 'Declarada'], array_map(
            fn (DOMElement $text): string => $this->uiText($text),
            iterator_to_array($xpath->query('//button[@data-event-type="maintenance"]//span[@class="sr-only"]')),
        ));
        $this->assertSame(0, $this->uiCount($xpath, '//*[contains(@class, "text-[11px]")]'), 'Nada abaixo de text-xs.');
        $this->assertSame('upcoming', $this->uiElement($xpath, '(//button[@role="tab"])[last()]')->getAttribute('data-event-type'));
    }

    public function test_summary_header_has_an_accessible_progressbar(): void
    {
        [$vehicle] = $this->vehicleWithHistory();
        $timeline = app(VehicleTimelineBuilder::class)->build($vehicle);

        $xpath = $this->renderUi('<x-vehicle-timeline :timeline="$timeline" />', ['timeline' => $timeline]);

        $progress = $this->uiElement($xpath, '//div[@role="progressbar"]');
        $this->assertSame('0', $progress->getAttribute('aria-valuemin'));
        $this->assertSame('100', $progress->getAttribute('aria-valuemax'));
        $this->assertMatchesRegularExpression('/^(Faltam|Revisão estimada)/', $progress->getAttribute('aria-valuetext'));
        $this->assertStringContainsString('Da mais antiga para a mais recente', $this->uiText($this->uiElement($xpath, '//section/div[1]/p')));

        $compact = $this->renderUi('<x-vehicle-timeline :timeline="$timeline" :summary="false" heading-level="h3" />', ['timeline' => $timeline]);
        $this->assertSame(0, $this->uiCount($compact, '//div[@role="progressbar"]'));
        $this->assertSame(1, $this->uiCount($compact, '//h3[@id="linha-do-tempo-titulo"]'));
        $this->assertGreaterThan(0, $this->uiCount($compact, '//div[@role="tabpanel"]//h4'));
    }

    public function test_filter_hides_columns_outside_it_and_keeps_a_visible_selection(): void
    {
        [$vehicle, , $declared] = $this->vehicleWithHistory();
        $vehicle->update(['current_kilometers' => 31000]);
        $timeline = app(VehicleTimelineBuilder::class)->build($vehicle->fresh());

        $xpath = $this->renderUi('<x-vehicle-timeline :timeline="$timeline" filter="1" />', ['timeline' => $timeline]);

        $declaredColumn = $this->uiElement($xpath, '//button[@data-verified="0"]');
        $this->assertTrue($declaredColumn->hasAttribute('hidden'));
        $this->assertTrue($declaredColumn->hasAttribute('disabled'));
        $this->assertSame('false', $declaredColumn->getAttribute('aria-selected'), 'A coluna escondida nunca fica selecionada.');
        $this->assertSame(1, $this->uiCount($xpath, '//button[@role="tab" and @aria-selected="true" and not(@hidden)]'));
        $this->assertTrue($this->uiElement($xpath, '//div[@data-timeline-progress]')->hasAttribute('hidden'));
        $this->assertStringNotContainsString('Troca de óleo caseira', $this->uiText($this->uiElement($xpath, '//div[@role="tabpanel" and not(@hidden)]')));
        $this->assertNotNull($declared);
    }

    public function test_footer_slot_renders_below_the_panels_and_nothing_renders_without_events(): void
    {
        [$vehicle] = $this->vehicleWithHistory();
        $timeline = app(VehicleTimelineBuilder::class)->build($vehicle);

        $xpath = $this->renderUi('<x-vehicle-timeline :timeline="$timeline"><x-slot:footer><p data-filter>Filtro</p></x-slot:footer></x-vehicle-timeline>', ['timeline' => $timeline]);
        $this->assertSame(1, $this->uiCount($xpath, '//section/div[@data-timeline-footer]/p[@data-filter]'));

        $empty = (string) $this->blade('<x-vehicle-timeline :timeline="$timeline" />', ['timeline' => ['events' => [], 'summary' => []]]);
        $this->assertSame('', trim($empty));
    }

    /**
     * @return array{Vehicle, Maintenance, Maintenance}
     */
    private function vehicleWithHistory(): array
    {
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 35000, 'odometer_at_registration' => 10000]);
        $declared = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Troca de óleo caseira', 'kilometers' => 30000, 'maintenance_date' => '2024-03-01']);
        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Revisão 20 mil', 'kilometers' => 20000, 'maintenance_date' => '2023-03-01']);

        return [$vehicle->fresh(), $sealed, $declared];
    }
}
