<?php

namespace Tests\Feature\Components;

use App\Enums\Portal;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePlate;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-vehicle.detail>: a ficha do veículo igual em todos os portais (LIVE-05, USR-15, GAR-20):
 * um H1, capa, resumo sem repetir o chassi, abas Linha do tempo | Histórico | Documentos, filtro de
 * procedência abaixo da linha do tempo e o histórico na mesma ordem dela.
 */
class VehicleDetailComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    private Vehicle $vehicle;

    private Maintenance $sealed;

    private Maintenance $declared;

    private Maintenance $latest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicle = Vehicle::factory()->create([
            'brand' => 'Volkswagen',
            'model' => 'Gol',
            'year' => 2019,
            'color' => 'Prata',
            'license_plate' => 'ABC1D23',
            'chassis' => '9BWZZZ377VT004251',
            'renavam' => '12345678901',
            'crv_number' => '998877665544',
            'current_kilometers' => 52000,
            'odometer_at_registration' => 10000,
        ]);

        // Criadas fora de ordem: a ficha ordena pela quilometragem, como a linha do tempo.
        $this->latest = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Troca de pastilhas',
            'kilometers' => 45000,
            'maintenance_date' => '2025-08-10',
        ]);
        $this->sealed = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Revisão 20 mil',
            'kilometers' => 20000,
            'maintenance_date' => '2023-02-01',
        ]);
        $this->declared = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'maintenance_type' => 'Troca de óleo caseira',
            'kilometers' => 30000,
            'maintenance_date' => '2024-01-15',
        ]);
    }

    public function test_detail_has_one_h1_the_identity_and_a_summary_without_repeating_the_chassis(): void
    {
        $xpath = $this->renderDetail();

        $this->assertSame(1, $this->uiCount($xpath, '//h1'));
        $this->assertSame('Volkswagen Gol', $this->uiText($this->uiElement($xpath, '//h1')));
        $this->assertSame('2019 · Prata · Placa ABC1D23', $this->uiText($this->uiElement($xpath, '//p[@data-slot="page-header-description"]')));

        $this->assertSame(1, substr_count($this->html, '9BWZZZ377VT004251'.'<'), 'O chassi aparece uma vez (identidade), não no resumo.');
        $this->assertSame(1, $this->uiCount($xpath, '//button[@data-copy-button][@data-copy-value="9BWZZZ377VT004251"]'), 'O dono copia o chassi pelo x-ui.copy-button.');

        $summary = $this->uiElement($xpath, '//div[@data-slot="vehicle-detail-summary"]');
        $labels = array_map(fn (DOMElement $label): string => $this->uiText($label), iterator_to_array($xpath->query('.//dt[@data-slot="stat-label"]', $summary)));
        $this->assertSame(['Km atual', 'Próxima revisão', 'Procedência', 'Total em itens'], $labels);
        $this->assertStringNotContainsString('Chassi', $this->uiText($summary));
        $this->assertSame('52.000 km', $this->uiText($this->uiElement($xpath, '(//dd[@data-slot="stat-value"])[1]')));
        $this->assertSame('1 com selo', $this->uiText($this->uiElement($xpath, '(//dd[@data-slot="stat-value"])[3]')));
        $this->assertSame('2 declaradas', $this->uiText($this->uiElement($xpath, '(//div[@data-slot="vehicle-detail-summary"]/*[@data-slot="stat"])[3]//p[@data-slot="stat-hint"]')));

        $progress = $this->uiElement($xpath, '//div[@data-slot="vehicle-detail-summary"]//div[@role="progressbar"]');
        $this->assertSame('Quilometragem até a próxima revisão', $progress->getAttribute('aria-label'));
        $this->assertNotSame('', $progress->getAttribute('aria-valuetext'));

        $this->assertSame('hero', $this->uiElement($xpath, '//*[@data-vehicle-cover]')->getAttribute('data-vehicle-cover'));
    }

    public function test_sections_are_tabs_with_the_timeline_open_and_the_url_in_sync(): void
    {
        $xpath = $this->renderDetail();

        $tabs = $xpath->query('//div[@role="tablist" and @aria-label="Seções do veículo"]/button[@role="tab"]');
        $this->assertSame(['linha-do-tempo', 'historico', 'documentos'], array_map(fn (DOMElement $tab): string => $tab->getAttribute('aria-controls'), iterator_to_array($tabs)));
        $this->assertSame('true', $tabs->item(0)->getAttribute('aria-selected'));
        $this->assertStringContainsString('3', $this->uiText($tabs->item(1)), 'A aba Histórico mostra quantas manutenções há.');
        $this->assertSame(1, $this->uiCount($xpath, '//div[@data-ui-tabs and @data-ui-tabs-sync-url]'));

        $this->assertFalse($this->uiElement($xpath, '//div[@id="linha-do-tempo"]')->hasAttribute('hidden'));
        $this->assertTrue($this->uiElement($xpath, '//div[@id="historico"]')->hasAttribute('hidden'));
        $this->assertTrue($this->uiElement($xpath, '//div[@id="documentos"]')->hasAttribute('hidden'));
    }

    public function test_history_follows_the_timeline_order_and_links_to_the_portal_detail(): void
    {
        $xpath = $this->renderDetail();

        $timelineTitles = array_map(
            fn (DOMElement $column): string => $this->uiText($column),
            iterator_to_array($xpath->query('//div[@id="timeline-colunas"]/button[@data-event-type="maintenance"]//span[@data-column-title]')),
        );
        $historyTitles = array_map(
            fn (DOMElement $title): string => $this->uiText($title),
            iterator_to_array($xpath->query('//ol[@id="historico-lista"]/li//*[@data-slot="provenance-card-title"]')),
        );

        $this->assertSame(['Revisão 20 mil', 'Troca de óleo caseira', 'Troca de pastilhas'], $timelineTitles);
        $this->assertSame($timelineTitles, $historyTitles, 'Histórico e linha do tempo na mesma ordem (LIVE-05).');
        $this->assertSame('Manutenções, da mais antiga à mais recente', $this->uiElement($xpath, '//ol[@id="historico-lista"]')->getAttribute('aria-label'));

        $this->assertSame(
            route('user.maintenances.show', $this->sealed),
            $this->uiElement($xpath, '//li[@id="manutencao-'.$this->sealed->id.'"]//a')->getAttribute('href'),
        );
        $this->assertSame(1, $this->uiCount($xpath, '//a[normalize-space()="Ver manutenção completa" and @href="'.route('user.maintenances.show', $this->declared).'"]'));
    }

    public function test_provenance_filter_sits_below_the_timeline_and_on_top_of_the_history(): void
    {
        $xpath = $this->renderDetail();

        $timelineFooter = $this->uiElement($xpath, '//section[@data-vehicle-timeline]/div[@data-timeline-footer]');
        $this->assertSame(1, $xpath->query('.//ol[contains(@class, "prov-dots-row")]', $timelineFooter)->length, 'Pontos no card da linha do tempo.');
        $this->assertSame(1, $xpath->query('.//form[@data-provenance-filter-form]', $timelineFooter)->length, 'Filtro abaixo da linha do tempo.');
        $dotsAndFilter = $xpath->query('./*', $timelineFooter);
        $this->assertStringContainsString('data-provenance-strip', $dotsAndFilter->item(0)->ownerDocument->saveHTML($dotsAndFilter->item(0)), 'Os pontos vêm logo acima do filtro.');
        $this->assertSame('form', $dotsAndFilter->item(1)->nodeName);
        $this->assertSame(0, $this->uiCount($xpath, '//div[@data-slot="vehicle-identity"]//ol'), 'Pontos nunca no bloco de identidade.');

        $forms = $xpath->query('//form[@data-provenance-filter-form]');
        $this->assertSame(2, $forms->length);

        foreach ($forms as $form) {
            $this->assertSame('get', $form->getAttribute('method'));
            $this->assertSame('off', $form->getAttribute('data-submit-busy'));
            $buttons = $xpath->query('.//button[@name="verified"]', $form);
            $this->assertSame(['', '1', '0'], array_map(fn (DOMElement $button): string => $button->getAttribute('value'), iterator_to_array($buttons)));
            $this->assertSame(['true', 'false', 'false'], array_map(fn (DOMElement $button): string => $button->getAttribute('aria-pressed'), iterator_to_array($buttons)));
            $this->assertSame('submit', $buttons->item(1)->getAttribute('type'));
        }

        $this->assertStringEndsWith('#linha-do-tempo', $forms->item(0)->getAttribute('action'));
        $this->assertStringEndsWith('#historico', $forms->item(1)->getAttribute('action'));
        $this->assertSame('historico-lista', $this->uiElement($xpath, '(//form[@data-provenance-filter-form])[2]//button[1]')->getAttribute('aria-controls'));
        $this->assertSame('Selo da oficina 1', $this->uiText($this->uiElement($xpath, '(//form[@data-provenance-filter-form])[2]//button[@value="1"]')));
    }

    public function test_verified_query_filters_history_and_timeline_on_the_server(): void
    {
        $xpath = $this->renderDetail(['filter' => '1']);

        $this->assertFalse($this->uiElement($xpath, '//li[@id="manutencao-'.$this->sealed->id.'"]')->hasAttribute('hidden'));
        $this->assertTrue($this->uiElement($xpath, '//li[@id="manutencao-'.$this->declared->id.'"]')->hasAttribute('hidden'));
        $this->assertTrue($this->uiElement($xpath, '//li[@id="manutencao-'.$this->latest->id.'"]')->hasAttribute('hidden'));

        foreach ($xpath->query('//button[@data-timeline-column and @data-verified="0"]') as $column) {
            $this->assertTrue($column->hasAttribute('hidden'));
            $this->assertTrue($column->hasAttribute('disabled'), 'Coluna fora do filtro sai do teclado das abas.');
        }
        $this->assertTrue($this->uiElement($xpath, '//div[@data-timeline-progress]')->hasAttribute('hidden'));

        $this->assertSame('Mostrando 1 de 3 manutenções', $this->uiText($this->uiElement($xpath, '//p[@data-provenance-count]')));
        $this->assertSame('polite', $this->uiElement($xpath, '//p[@data-provenance-count]')->getAttribute('aria-live'));
        $this->assertSame('true', $this->uiElement($xpath, '(//form[@data-provenance-filter-form])[2]//button[@value="1"]')->getAttribute('aria-pressed'));
        $this->assertTrue($this->uiElement($xpath, '//div[@data-provenance-empty]')->hasAttribute('hidden'));
    }

    public function test_filter_without_results_shows_the_filtered_empty_state_with_clear_action(): void
    {
        $this->sealed->forceFill(['verified_at' => null, 'registered_by_type' => 'owner'])->saveQuietly();

        $xpath = $this->renderDetail(['filter' => '1']);

        $empty = $this->uiElement($xpath, '//div[@data-provenance-empty]');
        $this->assertFalse($empty->hasAttribute('hidden'));
        $this->assertSame('Nenhuma manutenção com Selo da oficina', $this->uiText($this->uiElement($xpath, '//div[@data-provenance-empty]//*[@data-slot="empty-state-title"]')));
        $this->assertSame('Nenhuma manutenção declarada', $empty->getAttribute('data-title-declared'));
        $this->assertSame('Limpar filtros', $this->uiText($this->uiElement($xpath, '//a[@data-provenance-clear]')));
    }

    public function test_vehicle_without_history_shows_empty_states_with_the_portal_cta(): void
    {
        $vehicle = Vehicle::factory()->create(['current_kilometers' => null, 'odometer_at_registration' => null]);

        $html = (string) $this->blade(
            '<x-vehicle.detail :vehicle="$vehicle" :portal="$portal"><x-slot:empty-actions><a href="/nova" data-cta>Registrar manutenção</a></x-slot:empty-actions></x-vehicle.detail>',
            ['vehicle' => $vehicle, 'portal' => Portal::Owner],
        );
        $xpath = $this->parseHtml($html);

        $this->assertSame(2, $this->uiCount($xpath, '//div[@data-slot="empty-state"]//a[@data-cta]'), 'Linha do tempo e histórico vazios, os dois com o CTA.');
        $this->assertSame(0, $this->uiCount($xpath, '//form[@data-provenance-filter-form]'));
        $this->assertSame('Sem estimativa', $this->uiText($this->uiElement($xpath, '(//dd[@data-slot="stat-value"])[2]')));
        $this->assertSame('Não informada', $this->uiText($this->uiElement($xpath, '(//dd[@data-slot="stat-value"])[1]')));
    }

    public function test_documents_tab_lists_identifiers_and_the_plate_history_table(): void
    {
        VehiclePlate::query()->create(['vehicle_id' => $this->vehicle->id, 'plate' => 'OLD1A11', 'started_at' => '2019-01-01', 'ended_at' => '2023-05-01', 'source' => 'manual']);

        $xpath = $this->renderDetail();

        $documents = $this->uiElement($xpath, '//div[@id="documentos"]');
        $this->assertStringContainsString('12345678901', $this->uiText($documents));
        $this->assertStringContainsString('998877665544', $this->uiText($documents));

        $table = $this->uiElement($xpath, '//div[@id="documentos"]//div[@data-slot="table"]');
        $this->assertSame('md', $table->getAttribute('data-stack'));
        $this->assertSame('Histórico de placas', $table->getAttribute('aria-label'));
        $this->assertSame('De', $this->uiElement($xpath, '//div[@id="documentos"]//td[contains(., "01/01/2019")]')->getAttribute('data-label'));
    }

    /**
     * Origem da placa pelo glossário, nunca o valor interno (crlv_import, manual, backfill).
     */
    public function test_plate_history_source_uses_the_glossary(): void
    {
        VehiclePlate::query()->create(['vehicle_id' => $this->vehicle->id, 'plate' => 'OLD1A11', 'started_at' => '2019-01-01', 'ended_at' => '2021-05-01', 'source' => 'backfill']);
        VehiclePlate::query()->create(['vehicle_id' => $this->vehicle->id, 'plate' => 'MID2B22', 'started_at' => '2021-05-01', 'ended_at' => '2023-05-01', 'source' => 'crlv_import']);
        VehiclePlate::query()->create(['vehicle_id' => $this->vehicle->id, 'plate' => 'NEW3C33', 'started_at' => '2023-05-01', 'source' => 'manual']);

        $documents = $this->uiText($this->uiElement($this->renderDetail(), '//div[@id="documentos"]'));

        $this->assertStringContainsString('CRLV-e', $documents);
        $this->assertStringContainsString('Informada manualmente', $documents);
        $this->assertStringContainsString('Cadastro', $documents);

        foreach (['crlv_import', 'backfill', 'Importação CRLV', 'Migração'] as $internal) {
            $this->assertStringNotContainsString($internal, $documents);
        }

        $this->assertSame('Cadastro pelo app', \App\Models\VehiclePlate::sourceLabel('api'));
        $this->assertSame('Cadastro', \App\Models\VehiclePlate::sourceLabel(null));
    }

    public function test_public_result_uses_h2_masks_identifiers_and_expands_cards_without_links(): void
    {
        $xpath = $this->renderDetail(['portal' => null, 'headingLevel' => 'h2', 'masked' => true]);

        $this->assertSame(0, $this->uiCount($xpath, '//h1'));
        $this->assertSame('Volkswagen Gol', $this->uiText($this->uiElement($xpath, '//header[@data-slot="vehicle-detail-header"]/div/h2')));
        $this->assertStringNotContainsString('9BWZZZ377VT004251', $this->html);
        $this->assertStringContainsString('9BW••••••••••4251', $this->html);
        $this->assertStringNotContainsString('12345678901', $this->html);
        $this->assertStringContainsString('•••••••8901', $this->html);
        $this->assertStringNotContainsString('998877665544', $this->html, 'CRV fica fora para quem não é dono.');
        $this->assertSame(0, $this->uiCount($xpath, '//button[@data-copy-button]'));

        $this->assertSame(0, $this->uiCount($xpath, '//ol[@id="historico-lista"]//a'), 'Sem portal, os cards não levam a telas que exigem login.');
        $this->assertSame(0, $this->uiCount($xpath, '//a[normalize-space()="Ver manutenção completa"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//a[contains(@class, "prov-dot-link")]'));
        $this->assertSame(3, $this->uiCount($xpath, '//ol[contains(@class, "prov-dots-row")]/li/span[@class="sr-only"]'));
    }

    public function test_public_result_expands_services_with_only_the_after_photos(): void
    {
        \App\Models\MaintenanceItem::factory()->create(['maintenance_id' => $this->sealed->id, 'name' => 'Correia dentada']);
        \App\Models\MaintenancePhoto::factory()->create(['maintenance_id' => $this->sealed->id, 'subject' => 'vehicle', 'stage' => 'after', 'path' => 'maintenance-photos/depois.jpg']);
        \App\Models\MaintenancePhoto::factory()->create(['maintenance_id' => $this->sealed->id, 'subject' => 'part', 'stage' => 'before', 'path' => 'maintenance-photos/peca-antes.jpg']);

        $xpath = $this->renderDetail(['portal' => null, 'headingLevel' => 'h2']);

        $details = $this->uiElement($xpath, '//li[@id="manutencao-'.$this->sealed->id.'"]//details');
        $this->assertStringContainsString('Correia dentada', $this->uiText($details));
        $this->assertStringContainsString('depois.jpg', $this->html);
        $this->assertStringNotContainsString('peca-antes.jpg', $this->html, 'Foto que não é do carro depois do serviço não é pública.');
    }

    public function test_dealer_portal_legend_explains_both_declared_markers_and_custom_maintenance_url(): void
    {
        $xpath = $this->renderDetail(['portal' => Portal::Dealer, 'maintenanceUrl' => '/garagem/manutencoes/{id}']);

        $legend = $this->uiText($this->uiElement($xpath, '//div[@data-slot="provenance-legend"]'));
        $this->assertStringContainsString('Declarada pelo proprietário', $legend);
        $this->assertStringContainsString('Declarada pelo lojista', $legend);
        $this->assertSame('/garagem/manutencoes/'.$this->sealed->id, $this->uiElement($xpath, '//li[@id="manutencao-'.$this->sealed->id.'"]//a')->getAttribute('href'));
        $this->assertSame('garage', $this->uiElement($xpath, '//div[@data-vehicle-detail]')->getAttribute('data-portal'));
    }

    public function test_actions_and_notice_slots_and_breadcrumb(): void
    {
        $html = (string) $this->blade(<<<'BLADE'
            <x-vehicle.detail :vehicle="$vehicle" :portal="$portal" :breadcrumbs="[['Meus veículos', '/usuario/veiculos'], ['Volkswagen Gol']]">
                <x-slot:actions><a href="/nova" data-primary>Registrar manutenção</a></x-slot:actions>
                <x-slot:notice><p data-notice>Em consignação</p></x-slot:notice>
            </x-vehicle.detail>
            BLADE, ['vehicle' => $this->vehicle, 'portal' => Portal::Owner]);
        $xpath = $this->parseHtml($html);

        $this->assertSame(1, $this->uiCount($xpath, '//div[@data-slot="page-header-actions"]/a[@data-primary]'));
        $this->assertSame(1, $this->uiCount($xpath, '//div[@data-slot="vehicle-detail-notice"]/p[@data-notice]'));
        $this->assertSame('Volkswagen Gol', $this->uiText($this->uiElement($xpath, '//nav//*[@aria-current="page"]')));
    }

    public function test_detail_renders_inside_an_authenticated_portal_page_without_lazy_loading(): void
    {
        $owner = User::factory()->asUser()->create();
        $this->attachVehicleToUser($owner, $this->vehicle);
        $this->actingAs($owner);

        // Carregado junto com outro veículo: o preventLazyLoading barra qualquer relação não carregada.
        Vehicle::factory()->create();
        $vehicle = Vehicle::query()->orderBy('id')->get()->firstWhere('id', $this->vehicle->id);
        $html = (string) $this->blade('<x-vehicle.detail :vehicle="$vehicle" portal="user" :timeline="$timeline" />', [
            'vehicle' => $vehicle,
            'timeline' => app(\App\Services\Vehicle\VehicleTimelineBuilder::class)->build($this->vehicle->fresh()),
        ]);

        $this->assertStringContainsString('data-vehicle-detail', $html);
        $this->assertSame(3, substr_count($html, 'data-maintenance-card='));
    }

    private string $html = '';

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderDetail(array $props = []): DOMXPath
    {
        $props += ['portal' => Portal::Owner, 'filter' => null, 'headingLevel' => 'h1', 'masked' => false, 'maintenanceUrl' => null];

        $this->html = (string) $this->blade(
            '<x-vehicle.detail :vehicle="$vehicle" :portal="$portal" :filter="$filter" :heading-level="$headingLevel" :masked="$masked" :maintenance-url="$maintenanceUrl" />',
            ['vehicle' => $this->vehicle->fresh()] + $props,
        );

        return $this->parseHtml($this->html);
    }
}
