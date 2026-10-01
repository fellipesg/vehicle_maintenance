<?php

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Scripts dos componentes de domínio: o JS só acrescenta interatividade ao HTML do servidor
 * (USR-30, PUB-08). As regras puras rodam no Node; o resto é contrato de código.
 */
class ProvenanceScriptsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const MODULES = ['utils/html.js', 'provenance-ui.js', 'vehicle-timeline-portal.js'];

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_filter_rules_match_the_server(): void
    {
        $output = $this->runInNode(<<<'JS'
            import { matchesProvenanceFilter, nearestVisibleIndex, normalizeProvenanceFilter, provenanceCountText } from './provenance-ui.js';

            console.log(JSON.stringify({
                normalized: ['1', '0', 1, 'x', null, undefined, ''].map(normalizeProvenanceFilter),
                matches: [[true, '1'], ['1', '1'], ['0', '1'], ['0', '0'], [true, '0'], ['0', ''], [true, 'x']].map(([verified, filter]) => matchesProvenanceFilter(verified, filter)),
                nearest: [
                    nearestVisibleIndex([true, false, true], 1),
                    nearestVisibleIndex([false, false, true], 0),
                    nearestVisibleIndex([true, true], 1),
                    nearestVisibleIndex([false, false], 0),
                ],
                all: provenanceCountText({ shown: 1, filter: '', templateAll: '3 manutenções', templateFiltered: 'Mostrando {shown} de 3 manutenções' }),
                filtered: provenanceCountText({ shown: 1, filter: '1', templateAll: '3 manutenções', templateFiltered: 'Mostrando {shown} de 3 manutenções' }),
            }));
            JS);

        $this->assertSame(['1', '0', '1', '', '', '', ''], $output['normalized']);
        $this->assertSame([true, true, false, true, false, true, true], $output['matches']);
        $this->assertSame([0, 2, 1, -1], $output['nearest']);
        $this->assertSame('3 manutenções', $output['all']);
        $this->assertSame('Mostrando 1 de 3 manutenções', $output['filtered']);
    }

    /**
     * Os pontos, os cards e o filtro só existem no Blade (x-provenance-strip, x-provenance-card,
     * x-vehicle.detail): os moldes em JS do antigo portal do proprietário saíram, e o app.js liga o
     * filtro e a linha do tempo em todos os portais.
     */
    public function test_provenance_markup_comes_only_from_the_blade_components(): void
    {
        $provenance = File::get(resource_path('js/provenance-ui.js'));
        $timeline = File::get(resource_path('js/vehicle-timeline-portal.js'));
        $app = File::get(resource_path('js/app.js'));

        foreach (['renderProvenanceStrip', 'renderProvenanceCard', 'renderProvenanceMarker', 'renderVehicleIdentity', 'renderMaintenanceHistoryList', 'initProvenanceStripFilters'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $provenance);
        }

        foreach (['renderVehicleTimeline', 'mountVehicleTimeline'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $timeline);
        }

        $this->assertStringContainsString("import { initProvenanceFilters } from './provenance-ui';", $app);
        $this->assertStringContainsString("import { initVehicleDetails, initVehicleTimelines } from './vehicle-timeline-portal';", $app);

        foreach (['initProvenanceFilters', 'initVehicleTimelines', 'initVehicleDetails'] as $init) {
            $this->assertMatchesRegularExpression('/^\s+'.$init.'\(\);$/m', $app, "app.js liga {$init}() em todos os portais.");
        }

        $this->assertStringNotContainsString('initVehicleSearchFilters', $app);
    }

    public function test_server_rendered_filter_toggles_attributes_instead_of_rewriting_html(): void
    {
        $source = File::get(resource_path('js/provenance-ui.js'));

        $this->assertStringContainsString('export function initProvenanceFilters(root = document)', $source);
        $this->assertStringContainsString("filterRoot.dataset.provenanceFilterReady === 'true'", $source, 'Idempotente.');
        $this->assertStringContainsString("setAttribute('aria-pressed'", $source);
        $this->assertStringContainsString('window.history.replaceState', $source);
        $this->assertStringContainsString("new CustomEvent('provenance:filter-change'", $source);

        $applyFilter = Str::between($source, 'export function applyProvenanceFilter', 'export function initProvenanceFilters');
        $this->assertStringNotContainsString('innerHTML', $applyFilter, 'O filtro da ficha não redesenha a lista.');
        $this->assertStringContainsString('.hidden = ', $applyFilter);

        $this->assertStringNotContainsString('innerHTML', $source, 'Na busca e na ficha, os cards do servidor não são trocados (PUB-08).');
        $this->assertStringNotContainsString('renderProvenanceLegend', $source, 'Molde sem uso removido.');
    }

    public function test_timeline_script_only_enhances_the_server_markup(): void
    {
        $source = File::get(resource_path('js/vehicle-timeline-portal.js'));

        $this->assertStringContainsString('export function initVehicleTimelines(root = document)', $source);
        $this->assertStringContainsString('export function initVehicleDetails(root = document)', $source);
        $this->assertStringContainsString("timeline.dataset.timelineReady === 'true'", $source);
        $this->assertStringContainsString("'ui:tab-change'", $source);
        $this->assertStringContainsString("'provenance:filter-change'", $source);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $source);

        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringNotContainsString('text-[11px]', $source);
    }

    /**
     * @return array<string, mixed>
     */
    private function runInNode(string $script): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run the provenance scripts.');
        }

        $this->sandbox = storage_path('framework/testing/provenance-js-'.Str::random(12));

        foreach (self::MODULES as $module) {
            $source = File::get(resource_path("js/{$module}"));
            $source = preg_replace("#(from\s+'\.{1,2}/[^']+?)(?<!\.js)'#", "$1.js'", $source);

            File::ensureDirectoryExists(dirname("{$this->sandbox}/{$module}"));
            File::put("{$this->sandbox}/{$module}", $source);
        }

        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::put("{$this->sandbox}/runner.js", $script);

        $result = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }
}
