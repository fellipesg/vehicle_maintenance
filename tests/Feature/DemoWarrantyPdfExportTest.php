<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\AppStorage;
use App\Support\DemoWarrantyPdfValidator;
use App\Support\DemoWorkshopLogoGenerator;
use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class DemoWarrantyPdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_pdf_embeds_logos_and_warranty_snapshot_anchors(): void
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => DemoMaintenanceWarrantiesSeeder::PLATE,
            'current_kilometers' => 100_000,
            'odometer_at_registration' => 50_000,
        ]);

        $divesa = Workshop::factory()->create(['name' => 'Mercedes-Benz DIVESA Londrina']);
        $brothers = Workshop::factory()->create(['name' => 'Brothers Auto Service']);

        $divesaLogoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$divesa->id.'_divesa.jpg';
        $brothersLogoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$brothers->id.'_brothers.jpg';

        Storage::disk('r2')->put(
            $divesaLogoPath,
            DemoWorkshopLogoGenerator::jpeg('DIVESA', [0, 51, 153]),
        );
        Storage::disk('r2')->put(
            $brothersLogoPath,
            DemoWorkshopLogoGenerator::jpeg('Brothers', [180, 30, 30]),
        );

        $divesa->update(['logo_path' => $divesaLogoPath]);
        $brothers->update(['logo_path' => $brothersLogoPath]);

        $user = User::factory()->asUser()->create();

        $divesaMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $divesa->id,
            'workshop_name' => $divesa->name,
            'maintenance_date' => '2026-03-10',
            'maintenance_type' => 'Revisão B (Assyst B)',
            'kilometers' => 95_000,
        ]);

        $batteryItem = $divesaMaintenance->items()->create([
            'name' => 'Bateria 12V AGM 80AH',
            'quantity' => 1,
            'unit_price' => 2248.00,
            'total_price' => 2248.00,
        ]);

        $orderTemplate = WarrantyTemplate::factory()->forWorkshop($divesa)->orderScope()->create([
            'name' => DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA,
            'body' => 'Corpo OS DIVESA '.DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA,
            'duration_days' => 365,
        ]);

        $itemTemplate = WarrantyTemplate::factory()->forWorkshop($divesa)->itemScope()->create([
            'name' => DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA,
            'body' => 'Corpo peça DIVESA '.DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA,
            'duration_days' => 180,
        ]);

        MaintenanceWarranty::factory()->fromTemplate($orderTemplate, $divesaMaintenance)->create();
        MaintenanceWarranty::factory()->fromTemplate($itemTemplate, $divesaMaintenance, $batteryItem)->create();

        $orderTemplate->update(['name' => 'TEMPLATE-CATALOGO-ALTERADO']);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => $brothers->id,
            'workshop_name' => $brothers->name,
            'maintenance_date' => '2026-02-01',
            'maintenance_type' => 'Demo Brothers',
            'kilometers' => 96_000,
        ]);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'workshop_id' => null,
            'workshop_name' => 'Oficina Independente (sem logo)',
            'maintenance_date' => '2025-11-15',
            'maintenance_type' => 'Demo controle',
            'kilometers' => 94_000,
        ]);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
            $this->assertTrue(
                str_contains($file['content'], "\xFF\xD8\xFF"),
                'Expected embedded JPEG logo bytes in PDF.',
            );

            $text = (new Parser)->parseContent($file['content'])->getText();

            $this->assertStringContainsString(DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA, $text);
            $this->assertStringContainsString(DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA, $text);
            $this->assertStringNotContainsString('TEMPLATE-CATALOGO-ALTERADO', $text);
            $this->assertStringNotContainsString('Corpo OS DIVESA', $text);
            $this->assertStringNotContainsString('Corpo peça DIVESA', $text);
            $this->assertStringNotContainsString('Logo da oficina:', $text);

            $maintenanceCount = $vehicle->fresh()->maintenances->count();
            $pageCount = DemoWarrantyPdfValidator::countPages($file['content']);
            $this->assertGreaterThanOrEqual(1 + $maintenanceCount, $pageCount);

            $this->assertTrue(DemoWarrantyPdfValidator::isValid($file['content']));
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }
}
