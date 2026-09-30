<?php

namespace Tests\Feature\Components;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-provenance-strip>: <ol> com um ponto por manutenção, nome acessível por ponto e nada de
 * role="img" envolvendo links (DS-18, PUB-06, USR-17, GAR-32, GAR-03).
 */
class ProvenanceStripComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_dots_are_an_ordered_list_with_one_named_link_per_maintenance(): void
    {
        [$vehicle, $older, $newer] = $this->vehicle();

        $xpath = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" dot-href="/usuario/manutencoes/{id}" />', ['vehicle' => $vehicle]);

        $list = $this->uiElement($xpath, '//ol[contains(@class, "prov-dots-row")]');
        $this->assertSame('Procedência das manutenções, da mais antiga à mais recente', $list->getAttribute('aria-label'));
        $this->assertSame(0, $this->uiCount($xpath, '//*[@role="img"]'));

        $links = $xpath->query('//ol/li/a[contains(@class, "prov-dot-link")]');
        $this->assertSame(2, $links->length);
        $this->assertSame('/usuario/manutencoes/'.$older->id, $links->item(0)->getAttribute('href'));
        $this->assertSame('Selo da oficina, 10/01/2023', $links->item(0)->getAttribute('aria-label'));
        $this->assertSame('/usuario/manutencoes/'.$newer->id, $links->item(1)->getAttribute('href'));
        $this->assertSame('Declarada, 20/06/2024', $links->item(1)->getAttribute('aria-label'));
        $this->assertSame('true', $this->uiElement($xpath, '//a/span[contains(@class, "prov-dot")]')->getAttribute('aria-hidden'));
        $this->assertSame(0, $this->uiCount($xpath, '//a[@href="#" or starts-with(@href, "#/")]'), 'Nenhum link morto.');
    }

    public function test_without_dot_href_the_dots_are_text_not_links_and_the_filter_is_off(): void
    {
        [$vehicle] = $this->vehicle();

        $xpath = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" />', ['vehicle' => $vehicle]);

        $this->assertSame(0, $this->uiCount($xpath, '//a'));
        $this->assertSame(['Selo da oficina, 10/01/2023', 'Declarada, 20/06/2024'], array_map(
            fn ($text): string => $this->uiText($text),
            iterator_to_array($xpath->query('//ol/li/span[@class="sr-only"]')),
        ));
        $this->assertSame(0, $this->uiCount($xpath, '//*[@data-slot="segmented"]'), 'O filtro fica abaixo da linha do tempo, não na faixa.');
        $this->assertStringContainsString('1 com selo', $this->uiText($this->uiElement($xpath, '//p[contains(@class, "prov-strip-summary")]')));

        $withoutSummary = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" :summary="false" />', ['vehicle' => $vehicle]);
        $this->assertSame(0, $this->uiCount($withoutSummary, '//p[contains(@class, "prov-strip-summary")]'));
    }

    public function test_legacy_hash_prefix_links_to_the_card_on_the_page(): void
    {
        [$vehicle, $older] = $this->vehicle();

        $xpath = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" maintenance-path-prefix="#" />', ['vehicle' => $vehicle]);

        $this->assertSame('#manutencao-'.$older->id, $this->uiElement($xpath, '(//a[contains(@class, "prov-dot-link")])[1]')->getAttribute('href'));
    }

    public function test_legacy_filters_use_the_segmented_control_with_state(): void
    {
        [$vehicle] = $this->vehicle();

        $buttons = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" :interactive-filters="true" />', ['vehicle' => $vehicle]);
        $group = $this->uiElement($buttons, '//div[@role="group" and @aria-label="Filtrar por procedência"]');
        $this->assertSame(3, $buttons->query('.//button[@data-provenance-filter]', $group)->length);
        $this->assertSame('true', $this->uiElement($buttons, '//button[@data-provenance-filter=""]')->getAttribute('aria-pressed'));

        $links = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" filter-base-url="/garagem/estoque/1" />', ['vehicle' => $vehicle]);
        $this->assertSame('/garagem/estoque/1?verified=1', $this->uiElement($links, '//nav[@aria-label="Filtrar por procedência"]/a[normalize-space()="Selo da oficina"]')->getAttribute('href'));
        $this->assertSame('Todas', $this->uiText($this->uiElement($links, '//a[@aria-current="page"]')));
    }

    public function test_overflow_after_sixteen_dots_is_announced(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(18)->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $xpath = $this->renderUi('<x-provenance-strip :vehicle="$vehicle" />', ['vehicle' => $vehicle->fresh()]);

        $this->assertSame(17, $this->uiCount($xpath, '//ol/li'));
        $more = $this->uiElement($xpath, '//li[@class="prov-dots-more"]');
        $this->assertSame('+2', $this->uiText($this->uiElement($xpath, '//li[@class="prov-dots-more"]/span[@aria-hidden="true"]')));
        $this->assertSame('Mais 2 manutenções', $this->uiText($this->uiElement($xpath, '//li[@class="prov-dots-more"]/span[@class="sr-only"]')));
        $this->assertNotNull($more);
    }

    /**
     * @return array{Vehicle, Maintenance, Maintenance}
     */
    private function vehicle(): array
    {
        $vehicle = Vehicle::factory()->create();
        $newer = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'maintenance_date' => '2024-06-20']);
        $older = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'maintenance_date' => '2023-01-10']);

        return [$vehicle->fresh(), $older, $newer];
    }
}
