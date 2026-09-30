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

    public function test_public_vehicle_search_filters_maintenances_by_verified_query(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão declarada',
        ]);

        // Sem JS o filtro é do servidor: o card que não casa sai com hidden (o JS mostra de novo no lugar).
        $sealedOnly = $this->actingAs($user)
            ->get('/buscar-veiculo?identifier=ABC1D23&verified=1')
            ->assertOk()
            ->assertSee('Selo da oficina', false)
            ->assertSee('Mostrando 1 de 2 manutenções', false)
            ->getContent();
        $this->assertMatchesRegularExpression('#<li[^>]*data-verified="0"[^>]*hidden#', $sealedOnly);
        $this->assertDoesNotMatchRegularExpression('#<li[^>]*data-verified="1"[^>]*hidden#', $sealedOnly);

        $declaredOnly = $this->actingAs($user)
            ->get('/buscar-veiculo?identifier=ABC1D23&verified=0')
            ->assertOk()
            ->assertSee('Revisão declarada', false)
            ->getContent();
        $this->assertMatchesRegularExpression('#<li[^>]*data-verified="1"[^>]*hidden#', $declaredOnly);
        $this->assertDoesNotMatchRegularExpression('#<li[^>]*data-verified="0"[^>]*hidden#', $declaredOnly);
    }

    public function test_public_vehicle_search_uses_client_side_provenance_filters(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);

        $html = $this->actingAs($user)
            ->get('/buscar-veiculo?identifier=ABC1D23')
            ->assertOk()
            ->getContent();

        // A busca usa a ficha <x-vehicle.detail>: formulário GET com botões name="verified" que o
        // initProvenanceFilters (resources/js/provenance-ui.js) filtra no lugar.
        $this->assertStringContainsString('data-provenance-filter-root', $html);
        $this->assertStringContainsString('data-provenance-filter-form', $html);
        $this->assertMatchesRegularExpression('#<button[^>]*name="verified"[^>]*value="1"#', $html);
        $this->assertMatchesRegularExpression('#<input type="hidden" name="identifier" value="ABC1D23">#', $html);
        $this->assertStringNotContainsString('id="vehicle-search-maintenances-json"', $html);
        $this->assertStringNotContainsString('href="?verified=1"', $html);
    }

    public function test_provenance_strip_renders_compact_summary_and_dots(): void
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
        $this->assertStringContainsString('prov-strip-summary', $html);
        $this->assertStringContainsString('2</span> com selo', $html);
        $this->assertStringContainsString('1</span> declarada', $html);
        $this->assertStringContainsString('prov-dots-row', $html);
        $this->assertSame(
            3,
            preg_match_all('/class="prov-dot prov-dot--(verified|declared)"/', $html)
        );
        $this->assertStringNotContainsString('prov-strip-segment', $html);
    }

    public function test_provenance_strip_dots_collapse_overflow_after_sixteen(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(17)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);

        $vehicle->load('provenanceStripMaintenances');
        $vehicle->loadCount([
            'maintenances',
            'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
        ]);

        $html = Blade::render('<x-provenance-strip :vehicle="$vehicle" />', ['vehicle' => $vehicle]);

        $this->assertSame(16, preg_match_all('/class="prov-dot prov-dot--verified"/', $html));
        $this->assertStringContainsString('class="prov-dots-more"', $html);
        $this->assertStringContainsString('+1</span>', $html);
    }

    public function test_workshop_maintenances_index_shows_the_provenance_of_each_order(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshopUser->workshop->id,
        ])->fresh();

        // A lista da oficina é uma tabela: a procedência vem no filtro, em data-verified e no código do selo.
        $this->actingAs($workshopUser)
            ->get(route('workshop.maintenances.index'))
            ->assertOk()
            ->assertSee('Selo da oficina', false)
            ->assertSee('data-maintenance-row="'.$maintenance->id.'" data-verified="1"', false)
            ->assertSee($maintenance->verification_code, false)
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
        $this->assertStringContainsString('Um ponto por manutenção, da mais antiga à mais recente', $html);
        $this->assertStringContainsString('revisalog.com.br/v/{código}', $html);

        // Capa: contador compacto ("1 com selo · 1 declarada") + linha de pontos (um por manutenção).
        $this->assertStringContainsString('<span class="prov-counter--sealed">1 com selo</span>', $html);
        $this->assertStringContainsString('<span class="prov-counter--declared">1 declarada</span>', $html);
        $this->assertSame(
            $vehicle->maintenances->count(),
            preg_match_all('/class="prov-dot prov-dot--(verified|declared)"/', $html)
        );

        // OS: selo em bloco largo com código e URL de verificação; declarada com evidência.
        $this->assertStringContainsString('class="seal seal--verified"', $html);
        $this->assertStringContainsString('class="seal seal--declared"', $html);
        $this->assertStringContainsString('Registro feito pela própria oficina em', $html);
        $this->assertStringContainsString('não verificado por oficina cadastrada', $html);
        $this->assertMatchesRegularExpression('#revisalog\.com\.br/v/RVL-[A-Z0-9]{4}-[A-Z0-9]{2}#', $html);
    }

    /**
     * O marcador da declarada usa as letras da legenda (PR, LJ), nunca as iniciais de quem declarou:
     * a busca por placa e o /v/ são vistos por quem não é dono.
     */
    public function test_declared_marker_uses_generic_letters_instead_of_the_owner_initials(): void
    {
        $owner = User::factory()->create(['name' => 'Zélia Quintana']);
        $ownerRecord = Maintenance::factory()->declaredByOwner()->create(['user_id' => $owner->id, 'workshop_name' => 'Mecânica Xavier']);
        $garageRecord = Maintenance::factory()->declaredByGarage()->create(['user_id' => $owner->id]);

        $ownerHtml = Blade::render('<x-provenance-marker :maintenance="$maintenance" />', ['maintenance' => $ownerRecord->fresh()->load('user')]);
        $garageHtml = Blade::render('<x-provenance-marker :maintenance="$maintenance" />', ['maintenance' => $garageRecord->fresh()->load('user')]);
        $eventHtml = Blade::render('<x-provenance-marker :event="$event" />', ['event' => ['is_verified' => false, 'registered_by_type' => 'garage', 'provenance_label' => 'Declarada pelo lojista']]);

        $this->assertMatchesRegularExpression('#prov-marker--declared[^>]*>\s*PR\s*</div>#', $ownerHtml);
        $this->assertStringNotContainsString('ZQ', $ownerHtml);
        $this->assertStringNotContainsString('MX', $ownerHtml);
        $this->assertMatchesRegularExpression('#prov-marker--declared[^>]*>\s*LJ\s*</div>#', $garageHtml);
        $this->assertMatchesRegularExpression('#prov-marker--declared[^>]*>\s*LJ\s*</div>#', $eventHtml);

        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => Workshop::factory()->create(['name' => 'Silva Auto'])->id]);
        $this->assertMatchesRegularExpression('#prov-marker--verified[^>]*>\s*SA\s*</div>#', Blade::render('<x-provenance-marker :maintenance="$maintenance" />', ['maintenance' => $sealed->fresh()->load('verifiedWorkshop')]));
    }
}
