<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Scripts do portal do proprietário depois da migração para o servidor:
 *
 * - resources/js/utils/maintenance-kilometers.js calcula no navegador a mesma faixa de
 *   quilometragem que o VehicleMileageService valida (conferido contra o serviço real);
 * - resources/js/utils/vehicle-pdf-export.js monta o link do PDF como pede .ai/rules/js.md;
 * - resources/js/user-portal.js ficou só com a interatividade (nada de montar tela pela API).
 *
 * Os módulos rodam no Node, sem DOM.
 */
class OwnerPortalScriptsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_kilometer_bounds_match_the_server_validation(): void
    {
        $owner = User::factory()->asUser()->create()->refresh();
        $vehicle = Vehicle::factory()->create([
            'created_at' => '2026-01-10 12:00:00',
            'odometer_at_registration' => 50_000,
            'current_kilometers' => 60_000,
        ]);
        $owner->vehicles()->attach($vehicle->id, ['is_current_owner' => true, 'tenant_id' => $owner->tenant_id]);
        foreach ([['2025-08-01', 40_000], ['2026-03-15', 55_000], ['2026-06-12', 60_000]] as [$date, $kilometers]) {
            Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'maintenance_date' => $date,
                'kilometers' => $kilometers,
            ]);
        }

        // Os dados que o formulário recebe do servidor, lidos da própria página.
        $html = $this->actingAs($owner)->get(route('user.maintenances.create', ['vehicle_id' => $vehicle->id]))->assertOk()->getContent();
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);
        $mileage = (new DOMXPath($document))->query('//*[@data-owner-maintenance-form]')->item(0)->getAttribute('data-mileage');

        $dates = ['2025-05-01', '2025-09-01', '2026-02-01', '2026-04-01', '2026-07-01', null];
        $output = $this->runNode(['utils/maintenance-kilometers.js'], sprintf(<<<'JS'
            const { kilometerBounds, describeKilometerBounds } = await import('./utils/maintenance-kilometers.js');
            const vehicle = JSON.parse(%s)[0];
            const dates = %s;
            console.log(JSON.stringify(dates.map((date) => {
                const bounds = kilometerBounds(vehicle, date);
                return { date, floor: bounds.floor, ceiling: bounds.ceiling, text: describeKilometerBounds(bounds) };
            })));
            JS, json_encode($mileage), json_encode($dates)));

        $service = app(VehicleMileageService::class);
        $accepts = function (int $kilometers, ?string $date) use ($service, $vehicle): bool {
            try {
                $service->assertMaintenanceKilometers($vehicle->fresh(), $kilometers, $date);

                return true;
            } catch (ValidationException) {
                return false;
            }
        };

        foreach ($output as $case) {
            $label = $case['date'] ?? 'sem data';
            $this->assertTrue($accepts($case['floor'], $case['date']), "{$label}: o piso {$case['floor']} precisa passar no servidor.");

            if ($case['floor'] > 0) {
                $this->assertFalse($accepts($case['floor'] - 1, $case['date']), "{$label}: abaixo do piso o servidor recusa.");
            }

            if ($case['ceiling'] !== null) {
                $this->assertTrue($accepts($case['ceiling'], $case['date']), "{$label}: o teto {$case['ceiling']} precisa passar no servidor.");
                $this->assertFalse($accepts($case['ceiling'] + 1, $case['date']), "{$label}: acima do teto o servidor recusa.");
            } else {
                $this->assertTrue($accepts(9_999_999, $case['date']), "{$label}: sem teto, qualquer valor acima do piso passa.");
            }
        }

        $byDate = collect($output)->keyBy(fn (array $case): string => $case['date'] ?? 'sem-data');
        $this->assertSame('Até 40.000 km (registro de 01/08/2025).', $byDate['2025-05-01']['text']);
        $this->assertSame('Entre 40.000 km (registro de 01/08/2025) e 50.000 km (hodômetro no cadastro, em 10/01/2026).', $byDate['2025-09-01']['text']);
        $this->assertSame('Entre 50.000 km (hodômetro no cadastro, em 10/01/2026) e 55.000 km (registro de 15/03/2026).', $byDate['2026-02-01']['text']);
        $this->assertSame('Entre 55.000 km (registro de 15/03/2026) e 60.000 km (registro de 12/06/2026).', $byDate['2026-04-01']['text']);
        $this->assertSame('A partir de 60.000 km (registro de 12/06/2026).', $byDate['2026-07-01']['text']);
    }

    public function test_pdf_link_is_a_relative_same_site_path_ending_in_pdf(): void
    {
        $output = $this->runNode(['utils/vehicle-pdf-export.js'], <<<'JS'
            const { portalPdfUrl, pdfDownloadFilename } = await import('./utils/vehicle-pdf-export.js');
            console.log(JSON.stringify({
                portal: portalPdfUrl('abc-1', { download_portal_url: '/usuario/exportacoes-pdf/abc-1/historico_manutencoes_QOS6H54.pdf', filename: 'x.pdf' }),
                absolute: portalPdfUrl('abc-1', { download_portal_url: 'http://localhost:8000/usuario/exportacoes-pdf/abc-1/historico.pdf', filename: 'historico_ABC1D23.pdf' }),
                otherExport: portalPdfUrl('abc-1', { download_portal_url: '/usuario/exportacoes-pdf/zzz/historico.pdf', filename: 'historico.pdf' }),
                apiOnly: portalPdfUrl('abc-1', { download_url: 'https://cdn.example/file.pdf?sig=1' }),
                accents: pdfDownloadFilename('histórico manutenção'),
                empty: pdfDownloadFilename(null),
            }));
            JS);

        $this->assertSame('/usuario/exportacoes-pdf/abc-1/historico_manutencoes_QOS6H54.pdf', $output['portal']);
        $this->assertSame('/usuario/exportacoes-pdf/abc-1/historico_ABC1D23.pdf', $output['absolute'], 'URL absoluta (APP_URL) vira o caminho relativo.');
        $this->assertSame('/usuario/exportacoes-pdf/abc-1/historico.pdf', $output['otherExport']);
        $this->assertSame('/usuario/exportacoes-pdf/abc-1/historico_manutencoes.pdf', $output['apiOnly'], 'download_url nunca é usado.');
        $this->assertSame('historico_manutencao.pdf', $output['accents']);
        $this->assertSame('historico_manutencoes.pdf', $output['empty']);

        foreach ($output as $key => $value) {
            if (str_starts_with($value, '/')) {
                $this->assertMatchesRegularExpression('#^/usuario/exportacoes-pdf/[^/]+/[A-Za-z0-9._-]+\.pdf$#', $value, $key);
            }
        }
    }

    public function test_user_portal_script_only_adds_interactivity(): void
    {
        $portal = File::get(resource_path('js/user-portal.js'));

        $this->assertStringContainsString("import { portalPdfUrl } from './utils/vehicle-pdf-export';", $portal);
        $this->assertStringContainsString('apiClient.requestVehiclePdfExport', $portal);
        $this->assertStringContainsString('apiClient.getVehiclePdfExportStatus', $portal);
        $this->assertStringContainsString("toast({ variant: 'error'", $portal);

        // O link do PDF segue .ai/rules/js.md: sem download, sem navegar sozinho, sem blob.
        foreach (['.download', "'download'", 'location.assign', 'window.open', 'blob:', 'responseType', 'download_url', 'iframe'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, preg_replace('#^\s*(//|\*|/\*\*).*$#m', '', $portal), "user-portal.js não pode usar {$forbidden}.");
        }

        // Nenhuma tela é mais montada pela API no navegador.
        foreach (['getMyVehicles', 'getMaintenances', 'getWorkshops', 'getVehicleTimeline', 'Carregando', 'data-api-page', 'alert('] as $legacy) {
            $this->assertStringNotContainsString($legacy, $portal);
        }

        foreach (['user-portal.js', 'owner-maintenance-form.js', 'utils/maintenance-kilometers.js', 'utils/vehicle-pdf-export.js'] as $script) {
            $this->assertSame(0, preg_match('/\p{Emoji_Presentation}/u', File::get(resource_path('js/'.$script))), "{$script} sem emoji.");
        }
    }

    public function test_owner_views_no_longer_have_client_rendered_shells(): void
    {
        foreach (File::allFiles(resource_path('views/user')) as $view) {
            $source = $view->getContents();

            $this->assertStringNotContainsString('data-api-page', $source, $view->getRelativePathname());
            $this->assertStringNotContainsString('Carregando', $source, $view->getRelativePathname());
        }
    }

    /**
     * @param  list<string>  $modules  caminhos relativos a resources/js
     * @return array<mixed>
     */
    private function runNode(array $modules, string $script): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run the owner portal modules.');
        }

        $this->sandbox ??= storage_path('framework/testing/owner-portal-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');

        foreach ($modules as $module) {
            File::ensureDirectoryExists(dirname("{$this->sandbox}/{$module}"));
            File::put("{$this->sandbox}/{$module}", File::get(resource_path('js/'.$module)));
        }

        File::put("{$this->sandbox}/runner.js", $script);

        $result = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }
}
