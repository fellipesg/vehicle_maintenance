<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Tests\TestCase;

class VehicleMaintenancePdfDownloadFilenameTest extends TestCase
{
    public function test_download_filename_is_ascii_and_ends_with_pdf(): void
    {
        $vehicle = new Vehicle([
            'license_plate' => 'QOS6H54',
            'brand' => 'Citroën',
        ]);

        $filename = app(VehicleMaintenancePdfExporter::class)->downloadFilename($vehicle);

        $this->assertSame(
            'historico_manutencoes_QOS6H54_Citroen_'.now()->format('Y-m-d').'.pdf',
            $filename,
        );
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function test_attachment_disposition_appends_pdf_and_stays_ascii(): void
    {
        $disposition = app(VehicleMaintenancePdfExporter::class)
            ->attachmentDisposition('historico_QOS6H54_garantias');

        $this->assertStringContainsString('filename="historico_QOS6H54_garantias.pdf"', $disposition);
        $this->assertStringContainsString("filename*=UTF-8''historico_QOS6H54_garantias.pdf", $disposition);
    }
}
