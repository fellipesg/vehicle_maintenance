<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleMaintenancePdfExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_embeds_vehicle_cover_photo(): void
    {
        Storage::fake('public');

        $jpeg = $this->solidJpeg();
        Storage::disk('public')->put('vehicle-covers/capa.jpg', $jpeg);

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
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    public function test_pdf_generates_without_cover_when_vehicle_has_none(): void
    {
        Storage::fake('public');

        $vehicle = Vehicle::factory()->create(['cover_photo_path' => null]);
        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle);

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    private function solidJpeg(): string
    {
        $image = imagecreatetruecolor(32, 48);
        imagefilledrectangle($image, 0, 0, 31, 47, imagecolorallocate($image, 20, 64, 175));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        $jpeg = ob_get_clean();
        $this->assertIsString($jpeg);
        $this->assertNotSame('', $jpeg);

        return $jpeg;
    }
}
