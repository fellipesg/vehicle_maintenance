<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\AppStorage;
use App\Support\DemoWarrantyPdfValidator;
use App\Support\DemoWorkshopLogoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleMaintenancePdfExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_pdf_embeds_vehicle_cover_photo(): void
    {
        $jpeg = $this->solidJpeg();
        Storage::disk('r2')->put('vehicle-covers/capa.jpg', $jpeg);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Mercedes-Benz',
            'model' => 'C 180',
            'cover_photo_path' => 'vehicle-covers/capa.jpg',
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle);

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            $this->assertTrue(
                str_contains($file['content'], "\xFF\xD8\xFF"),
                'Expected the generated PDF to embed the JPEG cover photo.'
            );
            $this->assertGreaterThanOrEqual(
                2,
                DemoWarrantyPdfValidator::embeddedImageCount($file['content']),
                'Expected cover photo and Revisalog logo images in the PDF.'
            );
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_cover_path_for_pdf_prefers_landscape_when_available(): void
    {
        $vehicle = Vehicle::factory()->make([
            'cover_photo_path' => 'vehicle-covers/landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/portrait.jpg',
        ]);

        $this->assertSame('vehicle-covers/landscape.jpg', $vehicle->coverPathForPdf());
    }

    public function test_pdf_prefers_landscape_cover_when_available(): void
    {
        $landscape = $this->solidJpeg(800, 450);
        $portrait = $this->solidJpeg(450, 800);

        Storage::disk('r2')->put('vehicle-covers/landscape.jpg', $landscape);
        Storage::disk('r2')->put('vehicle-covers/portrait.jpg', $portrait);

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Mercedes-Benz',
            'model' => 'C 180',
            'cover_photo_path' => 'vehicle-covers/landscape.jpg',
            'cover_photo_portrait_path' => 'vehicle-covers/portrait.jpg',
        ]);

        $this->assertSame('vehicle-covers/landscape.jpg', $vehicle->coverPathForPdf());

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle);

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            $this->assertTrue(
                str_contains($file['content'], "\xFF\xD8\xFF"),
                'Expected the generated PDF to embed the landscape cover photo.'
            );
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_generates_without_cover_when_vehicle_has_none(): void
    {
        $vehicle = Vehicle::factory()->create(['cover_photo_path' => null]);
        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle);

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            $this->assertGreaterThanOrEqual(
                1,
                DemoWarrantyPdfValidator::embeddedImageCount($file['content']),
                'Expected Revisalog logo image on cover page.'
            );
            $this->assertStringContainsString(
                'Revisalog',
                DemoWarrantyPdfValidator::extractText($file['content'])
            );
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_uses_one_page_per_maintenance_plus_cover(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_date' => '2026-03-01',
            'kilometers' => 95_000,
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_date' => '2026-02-01',
            'kilometers' => 94_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $pageCount = DemoWarrantyPdfValidator::countPages($file['content']);
            $maintenanceCount = $vehicle->fresh()->maintenances->count();

            $this->assertSame(2, $maintenanceCount);
            $this->assertGreaterThanOrEqual(1 + $maintenanceCount, $pageCount);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_same_workshop_still_uses_separate_pages_per_maintenance(): void
    {
        $user = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Repetida']);
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);

        Maintenance::factory()->count(2)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'kilometers' => 95_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertGreaterThanOrEqual(3, DemoWarrantyPdfValidator::countPages($file['content']));
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_without_workshop_logo_does_not_break(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => null,
            'workshop_name' => 'Oficina Independente',
            'kilometers' => 94_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            $text = DemoWarrantyPdfValidator::extractText($file['content']);
            $this->assertStringNotContainsString('Logo da oficina:', $text);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_owner_maintenance_shows_revisalog_logo_on_letterhead_right(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => null,
            'workshop_name' => null,
            'kilometers' => 94_000,
        ]);

        $vehicle->load(['maintenances.workshop', 'maintenances.items', 'maintenances.invoices']);

        $html = view('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'coverImageSrc' => null,
            'workshopLogos' => [],
            'workshopLogoWidth' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH,
            'workshopLogoHeight' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT,
            'revisalogCoverLogoSrc' => null,
            'revisalogLogoSrc' => 'data:image/png;base64,AAAA',
        ])->render();

        $this->assertStringContainsString('Manutenção registrada pelo proprietário', $html);
        $this->assertStringNotContainsString('letterhead-logo-small', $html);
        $this->assertStringContainsString('class="letterhead-logo"', $html);
        $this->assertStringContainsString('alt="RevisaLog"', $html);
        $this->assertStringContainsString(
            'width="'.VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH.'"',
            $html,
        );
        $this->assertStringContainsString(
            'height="'.VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT.'"',
            $html,
        );

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $logoCells = $xpath->query('//td[contains(concat(" ", normalize-space(@class), " "), " letterhead-logo-cell ")]');
        $this->assertGreaterThanOrEqual(1, $logoCells->length);
        $ownerLogo = $xpath->query('.//img[@alt="RevisaLog"]', $logoCells->item(0));
        $this->assertSame(1, $ownerLogo->length);
        $this->assertStringContainsString('letterhead-logo', $ownerLogo->item(0)?->getAttribute('class') ?? '');
    }

    public function test_pdf_fetches_duplicate_workshop_logo_once_via_public_http(): void
    {
        $this->configurePublicCoversDisk();
        Cache::flush();

        $user = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_logo.jpg';
        $logoUrl = AppStorage::coversUrl($logoPath);
        $jpeg = DemoWorkshopLogoGenerator::jpeg('Logo', [30, 64, 175]);
        $workshop->update(['logo_path' => $logoPath]);

        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);
        Maintenance::factory()->count(2)->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'kilometers' => 95_000,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            $logoUrl => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => $request->url() === $logoUrl);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_fails_when_invoice_cannot_be_downloaded(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => 'invoices/missing.pdf',
            'file_name' => 'nota.pdf',
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $exporter = app(VehicleMaintenancePdfExporter::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível baixar as notas fiscais do storage');

        $exporter->generate($vehicle->fresh());
    }

    public function test_pdf_includes_maintenance_kilometers_and_date_in_text(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 120_000]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_type' => 'Revisão B (Akyuzel B)',
            'maintenance_date' => '2016-07-03',
            'kilometers' => 110_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $text = DemoWarrantyPdfValidator::extractText($file['content']);

            $this->assertStringContainsString('110.000', $text);
            $this->assertStringContainsString('km', $text);
            $this->assertStringContainsString('Revisão B (Akyuzel B)', $text);
            $this->assertStringContainsString('03/07/2016', $text);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_cover_includes_vehicle_current_kilometers_when_set(): void
    {
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 110_000]);
        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle);

        try {
            $text = DemoWarrantyPdfValidator::extractText($file['content']);

            $this->assertStringContainsString('Quilometragem:', $text);
            $this->assertStringContainsString('110.000', $text);
            $this->assertStringContainsString('km', $text);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_embeds_workshop_logo_when_present(): void
    {
        $user = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_logo.jpg';
        Storage::disk('r2')->put($logoPath, DemoWorkshopLogoGenerator::jpeg('Logo', [30, 64, 175]));
        $workshop->update(['logo_path' => $logoPath]);

        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'kilometers' => 95_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertTrue(str_contains($file['content'], "\xFF\xD8\xFF"));
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_view_uses_stacked_cover_split_os_rows_and_repeatable_headers(): void
    {
        $user = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Layout']);
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'maintenance_type' => 'Revisão B',
            'maintenance_date' => '2026-03-01',
            'kilometers' => 95_000,
        ]);

        $maintenance->items()->create([
            'name' => 'Filtro de óleo',
            'quantity' => 1,
            'unit_price' => 120.00,
            'total_price' => 120.00,
        ]);

        $vehicle->load([
            'maintenances.workshop',
            'maintenances.items',
            'maintenances.invoices',
            'maintenances.generalWarranty',
        ]);

        $html = view('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'coverImageSrc' => 'data:image/jpeg;base64,/9j/4AAQ',
            'workshopLogos' => [$maintenance->id => 'data:image/jpeg;base64,/9j/4AAQ'],
            'workshopLogoWidth' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH,
            'workshopLogoHeight' => VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT,
            'revisalogCoverLogoSrc' => null,
            'revisalogLogoSrc' => null,
        ])->render();

        $this->assertStringNotContainsString('band-inner', $html);
        $this->assertStringNotContainsString('letterhead-card', $html);
        $this->assertDoesNotMatchRegularExpression('/\bvehicle-cover\b(?!-photo)/', $html);
        $this->assertStringNotContainsString('height: 100%', $html);
        $this->assertDoesNotMatchRegularExpression('/\.cover-document\s*\{[^}]*page-break-inside:\s*avoid/', $html);
        $this->assertDoesNotMatchRegularExpression('/\.os-body-card\s*\{[^}]*page-break-inside:\s*avoid/', $html);
        $this->assertStringContainsString('.vehicle-cover-photo', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('height: auto', $html);
        $this->assertStringContainsString('.os-document thead', $html);
        $this->assertStringContainsString('.items-table thead', $html);
        $this->assertStringContainsString(
            'width: '.VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH.'px',
            $html,
        );
        $this->assertStringContainsString(
            'height: '.VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT.'px',
            $html,
        );
        $this->assertStringContainsString('letterhead-logo-cell', $html);

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        $logoImages = $xpath->query('//img[contains(concat(" ", normalize-space(@class), " "), " letterhead-logo ")]');
        $this->assertSame(1, $logoImages->length);
        $this->assertSame(
            (string) VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH,
            $logoImages->item(0)?->getAttribute('width'),
        );
        $this->assertSame(
            (string) VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT,
            $logoImages->item(0)?->getAttribute('height'),
        );

        $titleBands = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " title-band ")]');
        $this->assertGreaterThanOrEqual(2, $titleBands->length);

        foreach ($titleBands as $band) {
            $this->assertSame(0, $xpath->query('.//table', $band)->length, 'Title band must not nest tables.');
            $rows = $xpath->query('./tr | ./tbody/tr', $band);
            $this->assertSame(1, $rows->length);
            $cells = $xpath->query('./td', $rows->item(0));
            $this->assertSame(2, $cells->length);
        }

        $letterheads = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " letterhead ")]');
        $this->assertSame(1, $letterheads->length);
        $this->assertSame(0, $xpath->query('.//table', $letterheads->item(0))->length, 'Letterhead must not nest tables.');

        $coverPhotos = $xpath->query('//img[contains(concat(" ", normalize-space(@class), " "), " vehicle-cover-photo ")]');
        $this->assertSame(1, $coverPhotos->length);

        $coverBody = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " cover-body ")]')->item(0);
        $this->assertNotNull($coverBody);
        $coverChildren = iterator_to_array($coverBody->childNodes);
        $coverTags = array_values(array_filter(
            $coverChildren,
            fn (\DOMNode $node): bool => $node->nodeType === XML_ELEMENT_NODE,
        ));
        $this->assertSame('div', $coverTags[0]->nodeName);
        $this->assertStringContainsString('vehicle-section-title', $coverTags[0]->getAttribute('class'));
        $this->assertSame('img', $coverTags[1]->nodeName);
        $this->assertStringContainsString('vehicle-cover-photo', $coverTags[1]->getAttribute('class'));
        $this->assertSame('div', $coverTags[2]->nodeName);
        $this->assertStringContainsString('info-table-wrapper', $coverTags[2]->getAttribute('class'));

        $osDocuments = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " os-document ")]');
        $this->assertSame(1, $osDocuments->length);
        $osTbodyRows = $xpath->query('./tbody/tr', $osDocuments->item(0));
        $this->assertGreaterThanOrEqual(2, $osTbodyRows->length, 'OS tbody must split meta, items, and optional invoices into separate rows.');

        $itemsTables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " items-table ")]');
        $this->assertSame(1, $itemsTables->length);
        $this->assertSame(4, $xpath->query('./thead/tr/th', $itemsTables->item(0))->length);
    }

    public function test_cover_crop_uses_landscape_banner_dimensions(): void
    {
        $this->assertSame(760, VehicleMaintenancePdfExporter::COVER_CROP_WIDTH);
        $this->assertSame(228, VehicleMaintenancePdfExporter::COVER_CROP_HEIGHT);
    }

    public function test_pdf_normalizes_landscape_workshop_logo_to_portrait_crop(): void
    {
        $user = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_logo.jpg';
        Storage::disk('r2')->put($logoPath, $this->solidJpeg(400, 160));
        $workshop->update(['logo_path' => $logoPath]);

        $vehicle = Vehicle::factory()->create(['current_kilometers' => 100_000]);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'kilometers' => 95_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);

            $method = new \ReflectionMethod($exporter, 'workshopLogoSrcFromCopy');
            $copy = [
                'path' => Storage::disk('r2')->path($logoPath),
                'temporary' => false,
                'content' => Storage::disk('r2')->get($logoPath),
            ];
            $temps = [];
            $dataUri = $method->invokeArgs($exporter, [$copy, &$temps]);

            $this->assertIsString($dataUri);
            $this->assertStringStartsWith('data:image/jpeg;base64,', $dataUri);
            $bytes = base64_decode(substr($dataUri, strlen('data:image/jpeg;base64,')), true);
            $this->assertIsString($bytes);
            $size = getimagesizefromstring($bytes);
            $this->assertIsArray($size);
            $this->assertSame(
                VehicleMaintenancePdfExporter::WORKSHOP_LOGO_CROP_WIDTH * VehicleMaintenancePdfExporter::WORKSHOP_LOGO_EMBED_SCALE,
                $size[0],
            );
            $this->assertSame(
                VehicleMaintenancePdfExporter::WORKSHOP_LOGO_CROP_HEIGHT * VehicleMaintenancePdfExporter::WORKSHOP_LOGO_EMBED_SCALE,
                $size[1],
            );
            $exporter->cleanupTemps($temps);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_revisalog_letterhead_logo_is_cropped_without_top_tagline(): void
    {
        $exporter = app(VehicleMaintenancePdfExporter::class);
        $method = new \ReflectionMethod($exporter, 'revisalogLetterheadLogoSrc');
        $dataUri = $method->invoke($exporter);

        $this->assertIsString($dataUri);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $dataUri);

        $bytes = base64_decode(substr($dataUri, strlen('data:image/jpeg;base64,')), true);
        $this->assertIsString($bytes);
        $size = getimagesizefromstring($bytes);
        $this->assertIsArray($size);
        $this->assertSame(
            VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_WIDTH * VehicleMaintenancePdfExporter::WORKSHOP_LOGO_EMBED_SCALE,
            $size[0],
        );
        $this->assertSame(
            VehicleMaintenancePdfExporter::WORKSHOP_LOGO_DISPLAY_HEIGHT * VehicleMaintenancePdfExporter::WORKSHOP_LOGO_EMBED_SCALE,
            $size[1],
        );
    }

    private function solidJpeg(int $width = 32, int $height = 48): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 20, 64, 175));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        $jpeg = ob_get_clean();
        $this->assertIsString($jpeg);
        $this->assertNotSame('', $jpeg);

        return $jpeg;
    }
}
