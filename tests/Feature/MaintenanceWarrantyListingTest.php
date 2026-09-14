<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceWarrantyListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_show_lists_item_warranty_label(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshop->id]);
        $item = $maintenance->items()->create([
            'name' => 'Pastilha',
            'quantity' => 1,
        ]);
        MaintenanceWarranty::factory()->itemScope($item)->create([
            'maintenance_id' => $maintenance->id,
            'duration_days' => 90,
        ]);

        $this->actingAs($workshopUser)
            ->get(route('workshop.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Garantia até', false);
    }

    public function test_pdf_view_uses_snapshot_duration_not_current_template_duration(): void
    {
        Storage::fake('public');
        $this->fakeCoversDisk('r2');

        $workshop = \App\Models\Workshop::factory()->create([
            'logo_path' => AppStorage::WORKSHOP_LOGOS_PREFIX.'logo.jpg',
        ]);
        Storage::disk('r2')->put($workshop->logo_path, 'logo');

        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(10),
        ]);
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create([
            'duration_days' => 30,
            'name' => 'Snapshot Name',
            'body' => 'Snapshot body text',
        ]);
        MaintenanceWarranty::factory()->fromTemplate($template, $maintenance)->create();

        $template->update(['duration_days' => 999, 'name' => 'Changed']);

        $warranty = $maintenance->fresh()->generalWarranty;
        $this->assertNotNull($warranty);
        $this->assertSame('Snapshot Name', $warranty->name);
        $this->assertSame(30, $warranty->duration_days);

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh());

        try {
            $this->assertStringStartsWith('%PDF', $file['content']);
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }
}
