<?php

namespace Tests\Feature\Components;

use App\Enums\Portal;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-maintenance.list>: data, veículo (modelo + placa), km, oficina e procedência em cada linha,
 * toolbar de filtros na query string, estados vazios e paginação (USR-10, GAR-16).
 */
class MaintenanceListComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_list_layout_shows_vehicle_date_km_and_links_by_portal(): void
    {
        [$sealed, $declared] = $this->maintenances();

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="$maintenances" :portal="$portal" />', [
            'maintenances' => Maintenance::query()->orderBy('maintenance_date')->get(),
            'portal' => Portal::Owner,
        ]);

        $items = $xpath->query('//ol[@aria-label="Manutenções"]/li[@data-maintenance-card]');
        $this->assertSame(2, $items->length);
        $this->assertSame(route('user.maintenances.show', $sealed), $this->uiElement($xpath, '//li[@data-maintenance-card="'.$sealed->id.'"]//h3/a')->getAttribute('href'));
        $this->assertSame(route('user.vehicles.show', $sealed->vehicle_id), $this->uiElement($xpath, '//li[@data-maintenance-card="'.$sealed->id.'"]//p[@data-slot="provenance-card-details"]//a')->getAttribute('href'));

        $firstText = $this->uiText($items->item(0));
        $this->assertStringContainsString('Honda Civic', $firstText);
        $this->assertStringContainsString('CIV1C23', $firstText);
        $this->assertStringContainsString('10/05/2024', $firstText);
        $this->assertStringContainsString('42.000 km', $firstText);
        $this->assertFalse($items->item(0)->hasAttribute('id'), 'Numa lista agregada o card não reivindica o id #manutencao-{id}.');

        $toolbar = $this->uiElement($xpath, '//div[@data-slot="maintenance-list-toolbar"]');
        $this->assertSame(3, $xpath->query('.//nav[@aria-label="Filtrar por procedência"]/a', $toolbar)->length);
        $this->assertNotNull($declared);
    }

    public function test_default_filters_keep_the_other_query_parameters_and_drop_the_page(): void
    {
        $this->maintenances();
        $this->app->instance('request', Request::create('/usuario/manutencoes', 'GET', ['veiculo' => '5', 'page' => '3', 'verified' => '1']));

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="$maintenances" :counts="[\'\' => 2, \'1\' => 1, \'0\' => 1]" />', [
            'maintenances' => Maintenance::query()->whereNotNull('verified_at')->get(),
        ]);

        $current = $this->uiElement($xpath, '//nav[@aria-label="Filtrar por procedência"]/a[@aria-current="page"]');
        $this->assertStringStartsWith('Selo da oficina', $this->uiText($current));
        $this->assertStringContainsString('1', $this->uiText($this->uiElement($xpath, '//a[@aria-current="page"]/span[@data-slot="segmented-count"]')));
        $this->assertSame('http://localhost/usuario/manutencoes?veiculo=5&verified=0', html_entity_decode($this->uiElement($xpath, '//nav/a[contains(., "Declaradas")]')->getAttribute('href')));
        $this->assertSame('http://localhost/usuario/manutencoes?veiculo=5', html_entity_decode($this->uiElement($xpath, '//nav/a[contains(., "Todas")]')->getAttribute('href')));
    }

    public function test_vehicle_filter_is_a_get_form_with_a_labelled_select(): void
    {
        [$sealed] = $this->maintenances();
        $this->app->instance('request', Request::create('/usuario/manutencoes', 'GET', ['verified' => '0', 'busca' => 'óleo']));

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="$maintenances" :vehicles="$vehicles" />', [
            'maintenances' => Maintenance::all(),
            'vehicles' => Vehicle::all(),
        ]);

        $form = $this->uiElement($xpath, '//form[@aria-label="Filtrar por veículo"]');
        $this->assertSame('get', $form->getAttribute('method'));
        $select = $this->uiElement($xpath, '//form//select[@name="veiculo"]');
        $this->assertSame($select->getAttribute('id'), $this->uiElement($xpath, '//form//label')->getAttribute('for'));
        $this->assertSame('Veículo', $this->uiText($this->uiElement($xpath, '//form//label')));
        $this->assertSame(1, $this->uiCount($xpath, '//form//option[normalize-space()="Honda Civic · CIV1C23" and @value="'.$sealed->vehicle_id.'"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//form/input[@type="hidden" and @name="busca" and @value="óleo"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//form/input[@type="hidden" and @name="verified" and @value="0"]'));
    }

    public function test_table_layout_uses_the_stacked_table_with_row_headers(): void
    {
        [$sealed] = $this->maintenances();

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="$maintenances" layout="table" caption="Manutenções do estoque" maintenance-url="/garagem/manutencoes/{id}" :vehicle-url="false" />', [
            'maintenances' => Maintenance::all(),
        ]);

        $table = $this->uiElement($xpath, '//div[@data-slot="table"]');
        $this->assertSame('md', $table->getAttribute('data-stack'));
        $this->assertSame('Manutenções do estoque', $table->getAttribute('aria-label'));
        $this->assertSame(['Data', 'Serviço', 'Veículo', 'Km', 'Oficina', 'Procedência'], array_map(fn ($th): string => $this->uiText($th), iterator_to_array($xpath->query('//thead//th'))));

        $row = $this->uiElement($xpath, '//tr[@data-maintenance-row="'.$sealed->id.'"]');
        $this->assertSame('1', $row->getAttribute('data-verified'));
        $this->assertSame('/garagem/manutencoes/'.$sealed->id, $this->uiElement($xpath, '//tr[@data-maintenance-row="'.$sealed->id.'"]/th[@scope="row"]/a')->getAttribute('href'));
        $this->assertSame('42.000 km', $this->uiText($this->uiElement($xpath, '//tr[@data-maintenance-row="'.$sealed->id.'"]/td[@data-label="Km"]')));
        $this->assertStringContainsString('tabular-nums', $this->uiElement($xpath, '//tr[@data-maintenance-row="'.$sealed->id.'"]/td[@data-label="Km"]')->getAttribute('class'));
        $this->assertSame(0, $this->uiCount($xpath, '//td[@data-label="Veículo"]//a'), 'vehicle-url false: sem link no veículo.');
        $this->assertSame('seal', $this->uiElement($xpath, '//tr[@data-maintenance-row="'.$sealed->id.'"]//span[@data-slot="badge"]')->getAttribute('data-variant'));
    }

    public function test_group_by_month_adds_month_headings(): void
    {
        $this->maintenances();

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="$maintenances" group-by-month />', [
            'maintenances' => Maintenance::query()->orderByDesc('maintenance_date')->get(),
        ]);

        $this->assertSame(['Março de 2025', 'Maio de 2024'], array_map(fn ($heading): string => $this->uiText($heading), iterator_to_array($xpath->query('//section/h3'))));
        $this->assertSame(2, $this->uiCount($xpath, '//section/ol/li//h4'));

        // O mês fica preso logo abaixo da navbar sticky (h-16) de layouts.app, não atrás dela.
        $monthHeading = $this->uiElement($xpath, '//section/h3');
        $this->assertHasClasses(['sticky', 'top-16', 'z-10'], $monthHeading);
        $this->assertNotContains('top-0', explode(' ', $monthHeading->getAttribute('class')));
    }

    public function test_empty_states_distinguish_no_data_from_a_filter_without_results(): void
    {
        $empty = $this->renderUi('<x-maintenance.list :maintenances="collect()" empty-description="Registre a primeira."><x-slot:empty-actions><a href="/nova" data-cta>Registrar manutenção</a></x-slot:empty-actions></x-maintenance.list>');
        $this->assertSame('Nenhuma manutenção registrada', $this->uiText($this->uiElement($empty, '//*[@data-slot="empty-state-title"]')));
        $this->assertSame(1, $this->uiCount($empty, '//div[@data-maintenance-list-empty="all"]//a[@data-cta]'));

        $this->app->instance('request', Request::create('/usuario/manutencoes', 'GET', ['verified' => '1', 'ordem' => 'asc']));
        $filtered = $this->renderUi('<x-maintenance.list :maintenances="collect()" />');
        $this->assertSame('Nenhuma manutenção com Selo da oficina', $this->uiText($this->uiElement($filtered, '//*[@data-slot="empty-state-title"]')));
        $this->assertSame('http://localhost/usuario/manutencoes?ordem=asc', html_entity_decode($this->uiElement($filtered, '//div[@data-maintenance-list-empty="filtered"]//a')->getAttribute('href')));
    }

    public function test_custom_filter_options_with_a_default_value(): void
    {
        $this->app->instance('request', Request::create('/garagem/manutencoes', 'GET', ['filtro' => 'todas']));
        $options = [
            ['value' => 'todas', 'label' => 'Todas do estoque', 'href' => '/garagem/manutencoes', 'count' => 0],
            ['value' => 'minhas', 'label' => 'Registradas por mim', 'href' => '/garagem/manutencoes?filtro=minhas', 'count' => 0],
        ];

        $xpath = $this->renderUi('<x-maintenance.list :maintenances="collect()" :filter-options="$options" filter-param="filtro" filter-default="todas" filter-label="Filtrar manutenções" />', ['options' => $options]);

        $this->assertSame('Todas do estoque', trim(preg_replace('/\s+\d+$/', '', $this->uiText($this->uiElement($xpath, '//nav[@aria-label="Filtrar manutenções"]/a[@aria-current="page"]')))));
        $this->assertSame(1, $this->uiCount($xpath, '//div[@data-maintenance-list-empty="all"]'), 'filter-default não conta como filtro ativo.');

        $this->app->instance('request', Request::create('/garagem/manutencoes', 'GET', ['filtro' => 'minhas']));
        $filtered = $this->renderUi('<x-maintenance.list :maintenances="collect()" :filter-options="$options" filter-param="filtro" filter-default="todas" />', ['options' => $options]);
        $this->assertSame('Nenhuma manutenção neste filtro', $this->uiText($this->uiElement($filtered, '//*[@data-slot="empty-state-title"]')));
    }

    public function test_paginator_renders_links_with_the_query_string(): void
    {
        Maintenance::factory()->count(3)->declaredByOwner()->create();
        $this->app->instance('request', Request::create('/usuario/manutencoes', 'GET', ['verified' => '0']));

        $html = (string) $this->blade('<x-maintenance.list :maintenances="$maintenances" />', [
            'maintenances' => Maintenance::query()->paginate(2),
        ]);

        $this->assertStringContainsString('verified=0&amp;page=2', $html);
    }

    /**
     * @return array{Maintenance, Maintenance}
     */
    private function maintenances(): array
    {
        $civic = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);
        $gol = Vehicle::factory()->create(['brand' => 'VW', 'model' => 'Gol', 'license_plate' => 'GOL2B34']);

        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $civic->id, 'maintenance_date' => '2024-05-10', 'kilometers' => 42000]);
        $declared = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $gol->id, 'maintenance_date' => '2025-03-02', 'kilometers' => 61000]);

        return [$sealed, $declared];
    }
}
