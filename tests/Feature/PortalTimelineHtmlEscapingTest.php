<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class PortalTimelineHtmlEscapingTest extends TestCase
{
    private const PAYLOAD = '"><img src=x onerror=alert(1)>';

    private const ESCAPED_PAYLOAD = '&quot;&gt;&lt;img src=x onerror=alert(1)&gt;';

    /**
     * Modules copied to the Node sandbox, relative to resources/js.
     *
     * @var list<string>
     */
    private const MODULES = [
        'utils/html.js',
    ];

    /**
     * Scripts da ficha do veículo: só acrescentam interatividade ao HTML do servidor.
     *
     * @var list<string>
     */
    private const VEHICLE_PAGE_SCRIPTS = [
        'provenance-ui.js',
        'vehicle-timeline-portal.js',
    ];

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    /**
     * Um só escapeHtml (resources/js/utils/html.js): quem monta HTML com dados importa o helper, e
     * nenhum módulo guarda uma cópia própria.
     */
    public function test_portal_modules_share_a_single_escape_html_helper(): void
    {
        $helper = File::get(resource_path('js/utils/html.js'));

        $this->assertStringContainsString('export function escapeHtml(value)', $helper);

        foreach (File::allFiles(resource_path('js')) as $file) {
            $module = str_replace('\\', '/', $file->getRelativePathname());

            if ($module === 'utils/html.js' || $file->getExtension() !== 'js') {
                continue;
            }

            $source = $file->getContents();

            $this->assertStringNotContainsString('function escapeHtml', $source, "{$module} must not keep a private escapeHtml copy.");

            if (preg_match('/\bescapeHtml\(/', $source) === 1) {
                $this->assertMatchesRegularExpression("#import \{[^}]*\bescapeHtml\b[^}]*\} from '\.{1,2}/(?:utils/)?html';#", $source, "{$module} must import the shared escapeHtml.");
            }
        }
    }

    public function test_escape_html_covers_markup_quotes_and_ampersands(): void
    {
        $output = $this->runInNode(<<<'JS'
            import { escapeHtml } from './utils/html.js';

            console.log(JSON.stringify({
                markup: escapeHtml(`<b class="x">Tom & 'Jerry'</b>`),
                empty: escapeHtml(null),
                number: escapeHtml(3),
            }));
            JS);

        $this->assertSame('&lt;b class=&quot;x&quot;&gt;Tom &amp; &#39;Jerry&#39;&lt;/b&gt;', $output['markup']);
        $this->assertSame('', $output['empty']);
        $this->assertSame('3', $output['number']);
    }

    /**
     * A linha do tempo e o filtro de procedência não montam mais HTML no navegador (o portal do
     * proprietário deixou de desenhar a ficha pela API): os textos do banco só passam pelo Blade.
     */
    public function test_vehicle_timeline_script_builds_no_html_from_api_data(): void
    {
        $source = File::get(resource_path('js/vehicle-timeline-portal.js'));

        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringNotContainsString('insertAdjacentHTML', $source);
        $this->assertDoesNotMatchRegularExpression('/`\s*<[a-z]/i', $source, 'Nenhum molde de HTML em template string.');

        foreach (['renderVehicleTimeline', 'mountVehicleTimeline', 'renderTimelineItems', 'renderProvenanceMarker'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $source);
        }
    }

    /**
     * O componente Blade x-vehicle-timeline é renderizado no servidor: cada evento tem o próprio
     * painel e a troca é feita por resources/js/ui/tabs.js. Sem script inline, sem JSON e sem
     * innerHTML, os textos do banco passam só pelo escape do Blade.
     */
    public function test_blade_timeline_component_renders_on_the_server_without_inline_script(): void
    {
        $source = File::get(resource_path('views/components/vehicle-timeline.blade.php'));

        $this->assertStringNotContainsString('<script', $source);
        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringNotContainsString('@json', $source);
        $this->assertStringNotContainsString('{!!', $source);

        $html = (string) $this->blade('<x-vehicle-timeline :timeline="$timeline" />', ['timeline' => [
            'vehicle' => ['brand' => 'VW', 'model' => 'Gol'],
            'summary' => ['maintenance_count' => 1, 'total_spent' => 50],
            'events' => [[
                'type' => 'maintenance',
                'id' => 7,
                'is_current' => true,
                'is_verified' => false,
                'date' => '2026-01-15',
                'kilometers' => 20000,
                'label' => self::PAYLOAD,
                'workshop_name' => self::PAYLOAD,
                'provenance_label' => 'Declarada pelo proprietário',
                'total_amount' => 50,
                'items_count' => 1,
                'items' => [['name' => 'Filtro <b>óleo</b>', 'quantity' => 1, 'total_price' => 50]],
            ]],
        ]]);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>óleo', $html);
        $this->assertStringContainsString('Filtro &lt;b&gt;óleo&lt;/b&gt;', $html);
        $this->assertStringContainsString(e(self::PAYLOAD), $html);
    }

    public function test_vehicle_page_scripts_do_not_build_html(): void
    {
        foreach (self::VEHICLE_PAGE_SCRIPTS as $module) {
            $source = File::get(resource_path("js/{$module}"));

            $this->assertStringNotContainsString('innerHTML', $source, "{$module} só troca atributos do HTML do servidor.");
            $this->assertDoesNotMatchRegularExpression('/`\s*<[a-z]/i', $source, "{$module}: nenhum molde de HTML em template string.");
            $this->assertDoesNotMatchRegularExpression('/export function render[A-Z]/', $source, "{$module}: os moldes em JS da ficha saíram; a ficha é x-vehicle.detail.");
        }

        foreach (['renderProvenanceCard', 'renderProvenanceStrip', 'renderVehicleIdentity', 'renderMaintenanceHistoryList', 'initProvenanceStripFilters', 'filterMaintenancesByVerifiedQuery'] as $legacy) {
            $this->assertStringNotContainsString($legacy, File::get(resource_path('js/provenance-ui.js')));
        }

        $this->assertFileDoesNotExist(resource_path('js/vehicle-search-filters.js'), 'A busca usa x-vehicle.detail; o filtro legado saiu.');
    }

    /**
     * Run an ES module snippet against copies of the portal modules and decode its JSON output.
     *
     * Vite resolves extensionless relative imports; Node does not, so the copies get `.js` appended.
     *
     * @return array<string, mixed>
     */
    private function runInNode(string $script): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to render the portal modules.');
        }

        $this->sandbox = storage_path('framework/testing/portal-js-'.Str::random(12));

        foreach (self::MODULES as $module) {
            $source = File::get(resource_path("js/{$module}"));
            $source = preg_replace("#(from\s+'\.{1,2}/[^']+?)(?<!\.js)'#", "$1.js'", $source);

            File::ensureDirectoryExists(dirname("{$this->sandbox}/{$module}"));
            File::put("{$this->sandbox}/{$module}", $source);
        }

        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::put("{$this->sandbox}/runner.js", $script);

        $result = Process::path($this->sandbox)->run(['node', 'runner.js', self::PAYLOAD]);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }
}
