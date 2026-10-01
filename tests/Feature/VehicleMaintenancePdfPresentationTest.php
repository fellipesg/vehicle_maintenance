<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\DemoWarrantyPdfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Apresentação do histórico em PDF: capa com o contador compacto de procedência e a linha de pontos
 * (.ai/rules/theme.md), paleta automotive, fuso de Brasília, estado vazio na própria capa, rodapé
 * "Página X de Y" em toda página e nenhum SVG (o Dompdf não desenha QR em SVG de forma confiável).
 */
class VehicleMaintenancePdfPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cover_shows_the_compact_provenance_counter_and_dot_line(): void
    {
        $vehicle = $this->vehicleWithMaintenances(sealed: 3, declared: 1);

        $html = $this->renderView($vehicle);

        $this->assertStringContainsString('<span class="prov-counter--sealed">3 com selo</span>', $html);
        $this->assertStringContainsString('<span class="prov-counter--declared">1 declarada</span>', $html);
        $this->assertSame(4, preg_match_all('/class="prov-dot prov-dot--(verified|declared)"/', $html));
        $this->assertMatchesRegularExpression('/\.prov-dot \{\s*width: 10px;\s*height: 10px;/', $html);
        $this->assertMatchesRegularExpression('/\.prov-dots td \{[^}]*padding: 0 2px;/', $html);
        $this->assertStringContainsString('da mais antiga à mais recente', $html);
        $this->assertStringNotContainsString('prov-summary', $html);
        $this->assertStringNotContainsString('prov-count-num', $html);
        $this->assertStringNotContainsString('Com selo de oficina', $html);
    }

    public function test_pdf_uses_the_automotive_palette_and_has_no_svg(): void
    {
        $vehicle = $this->vehicleWithMaintenances(sealed: 1, declared: 1);

        $html = $this->renderView($vehicle);

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('<title>Histórico de manutenções · '.$vehicle->brand.' '.$vehicle->model.' · RevisaLog</title>', $html);
        $this->assertStringContainsString('color: #344453;', $html);
        $this->assertStringContainsString('color: #5a7289;', $html);

        foreach (['#333;', '#666;', '#999', '#6b7280', '#4b5563', '#111827', '#e5e7eb', '#f9fafb'] as $neutralGray) {
            $this->assertStringNotContainsString($neutralGray, $html);
        }

        // O timbre da OS mantém a borda da regra de .ai/rules/pdfs.md.
        $this->assertMatchesRegularExpression('/\.letterhead \{[^}]*border: 1px solid #d1d5db;/', $html);
    }

    public function test_dates_are_shown_in_brasilia_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 02:30:00', 'UTC'));

        $vehicle = $this->vehicleWithMaintenances(sealed: 1, declared: 0);
        $vehicle->maintenances()->first()->forceFill(['verified_at' => Carbon::parse('2026-03-02 01:00:00', 'UTC')])->saveQuietly();

        $html = $this->renderView($vehicle->fresh());

        $this->assertStringContainsString('Gerado em 01/03/2026 23:30', $html);
        $this->assertStringContainsString('Registro feito pela própria oficina em 01/03/2026', $html);
        $this->assertStringContainsString('em 01/03/2026 às 23:30', $html);
    }

    public function test_category_uses_the_service_category_labels(): void
    {
        $vehicle = $this->vehicleWithMaintenances(sealed: 0, declared: 1);
        $vehicle->maintenances()->first()->forceFill(['service_category' => 'suspension'])->saveQuietly();

        $html = $this->renderView($vehicle->fresh());

        $this->assertMatchesRegularExpression('/<span class="info-label">Categoria:<\/span>\s*<span class="info-value">Suspensão<\/span>/u', $html);
    }

    public function test_vehicle_without_maintenances_fits_on_the_cover(): void
    {
        $vehicle = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Uno', 'license_plate' => 'XYZ9A87']);

        $html = $this->renderView($vehicle);
        $this->assertStringContainsString('cover-document cover-document--only', $html);
        $this->assertMatchesRegularExpression('/<div class="cover-body">.*Nenhuma manutenção registrada para este veículo/s', $html);

        $file = $this->exporter()->generate($vehicle);

        try {
            $this->assertSame(1, DemoWarrantyPdfValidator::countPages($file['content']));

            $text = DemoWarrantyPdfValidator::extractText($file['content']);

            $this->assertStringContainsString('Nenhuma manutenção registrada para este veículo', $text);
            $this->assertStringContainsString('RevisaLog · Página 1 de 1', $text);
            $this->assertStringContainsString('Fiat Uno · XYZ9A87', $text);
        } finally {
            $this->exporter()->cleanupTemps($file['temps']);
        }
    }

    public function test_every_page_is_numbered_and_identifies_the_vehicle(): void
    {
        $vehicle = $this->vehicleWithMaintenances(sealed: 1, declared: 1);

        $file = $this->exporter()->generate($vehicle);

        try {
            $this->assertSame(3, DemoWarrantyPdfValidator::countPages($file['content']));

            $text = DemoWarrantyPdfValidator::extractText($file['content']);

            foreach ([1, 2, 3] as $page) {
                $this->assertStringContainsString("RevisaLog · Página {$page} de 3", $text);
            }

            $this->assertSame(3, substr_count($text, "{$vehicle->brand} {$vehicle->model} · {$vehicle->license_plate}"));
            $this->assertStringContainsString('1 com selo', $text);
            $this->assertStringContainsString('1 declarada', $text);
        } finally {
            $this->exporter()->cleanupTemps($file['temps']);
        }
    }

    private function vehicleWithMaintenances(int $sealed, int $declared): Vehicle
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'ABC1D23']);
        $attributes = ['vehicle_id' => $vehicle->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id];

        if ($sealed > 0) {
            Maintenance::factory()->count($sealed)->sealedByWorkshop()->create($attributes);
        }

        if ($declared > 0) {
            Maintenance::factory()->count($declared)->declaredByOwner()->create($attributes);
        }

        return $vehicle->fresh();
    }

    private function renderView(Vehicle $vehicle): string
    {
        $vehicle->load([
            'maintenances.items.warranty',
            'maintenances.generalWarranty',
            'maintenances.invoices',
            'maintenances.workshop',
            'maintenances.verifiedWorkshop',
        ]);

        return view('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'coverImageSrc' => null,
            'workshopLogos' => [],
            'workshopLogoWidth' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH,
            'workshopLogoHeight' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT,
            'revisalogCoverLogoSrc' => null,
            'revisalogLogoSrc' => null,
        ])->render();
    }

    private function exporter(): VehicleMaintenancePdfExporter
    {
        return app(VehicleMaintenancePdfExporter::class);
    }
}
