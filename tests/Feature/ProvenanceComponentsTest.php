<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\AppStorage;
use App\Support\VehicleProvenanceStrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProvenanceComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provenance_marker_renders_workshop_logo_when_logo_exists(): void
    {
        Storage::fake('r2');

        $workshop = Workshop::factory()->create();
        $logoPath = AppStorage::WORKSHOP_LOGOS_PREFIX.$workshop->id.'_marker_test.jpg';
        Storage::disk('r2')->put($logoPath, 'fake-logo');
        $workshop->update(['logo_path' => $logoPath]);

        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshop->id,
            'verified_workshop_id' => $workshop->id,
        ]);

        $html = Blade::render('<x-provenance-marker :maintenance="$maintenance" />', [
            'maintenance' => $maintenance->load('verifiedWorkshop'),
        ]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString($workshop->logoUrl(), $html);
    }

    public function test_provenance_marker_renders_filled_and_ring_variants(): void
    {
        $sealed = Maintenance::factory()->sealedByWorkshop()->create();
        $declared = Maintenance::factory()->declaredByOwner()->create();

        $sealedHtml = Blade::render('<x-provenance-marker :maintenance="$maintenance" />', [
            'maintenance' => $sealed->load('verifiedWorkshop'),
        ]);
        $declaredHtml = Blade::render('<x-provenance-marker :maintenance="$maintenance" />', [
            'maintenance' => $declared,
        ]);

        $this->assertStringContainsString('prov-marker--verified', $sealedHtml);
        $this->assertStringContainsString('prov-marker--declared', $declaredHtml);
    }

    public function test_provenance_strip_renders_one_segment_per_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(2)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $vehicle->load('provenanceStripMaintenances');
        $vehicle->loadCount([
            'maintenances',
            'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
        ]);

        $html = Blade::render('<x-provenance-strip :vehicle="$vehicle" />', ['vehicle' => $vehicle]);

        $this->assertSame(3, count(VehicleProvenanceStrip::segmentsForVehicle($vehicle)));
        $this->assertEquals(3, substr_count($html, 'class="prov-strip-segment '));
    }

    public function test_workshop_maintenances_index_includes_provenance_legend_and_cards(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshopUser->workshop->id,
        ]);

        $this->actingAs($workshopUser)
            ->get(route('workshop.maintenances.index'))
            ->assertOk()
            ->assertSee('Selo da oficina', false)
            ->assertSee('prov-card', false)
            ->assertSee($maintenance->maintenance_type, false);
    }

    public function test_pdf_view_includes_provenance_summary_and_footer(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);
        $vehicle->load([
            'maintenances.items.warranty',
            'maintenances.generalWarranty',
            'maintenances.invoices',
            'maintenances.checklists',
            'maintenances.workshop',
            'maintenances.verifiedWorkshop',
        ]);

        $html = view('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'coverImageSrc' => null,
            'workshopLogos' => [],
            'workshopLogoWidth' => 120,
            'workshopLogoHeight' => 130,
            'revisalogCoverLogoSrc' => null,
            'revisalogLogoSrc' => null,
        ])->render();

        $this->assertStringContainsString('Selo da oficina', $html);
        $this->assertStringContainsString('Declarada', $html);
        $this->assertStringContainsString('manutenções com selo de oficina', $html);
        $this->assertStringContainsString('revisalog.com.br/v/{código}', $html);
    }
}
