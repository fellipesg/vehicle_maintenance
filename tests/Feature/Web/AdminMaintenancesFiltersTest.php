<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Manutenções do admin: busca (placa, veículo, oficina, serviço), período, procedência com contagem,
 * filtros de contexto removíveis (conta, veículo, oficina), links na linha e ordenação por data.
 */
class AdminMaintenancesFiltersTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_search_matches_plate_vehicle_workshop_and_service_ignoring_case(): void
    {
        $corolla = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Corolla', 'license_plate' => 'ABC1D23']);
        $onix = Vehicle::factory()->create(['brand' => 'Chevrolet', 'model' => 'Onix', 'license_plate' => 'XYZ9Z88']);
        $workshop = Workshop::factory()->create(['name' => 'Auto Center Norte']);
        Maintenance::factory()->for($corolla)->create(['maintenance_type' => 'Troca de óleo', 'workshop_name' => null]);
        Maintenance::factory()->for($onix)->create(['maintenance_type' => 'Alinhamento', 'workshop_id' => $workshop->id, 'workshop_name' => null]);
        Maintenance::factory()->for($onix)->create(['maintenance_type' => 'Revisão elétrica', 'workshop_name' => 'Oficina do Bairro']);

        $admin = $this->adminUser();

        $this->assertSame(['Troca de óleo'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['q' => 'abc-1d23']))));
        $this->assertSame(['Troca de óleo'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['q' => 'corolla']))));
        $this->assertSame(['Alinhamento'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['q' => 'CENTER norte']))));
        $this->assertSame(['Revisão elétrica'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['q' => 'do bairro']))));
        $this->assertSame(['Troca de óleo'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['q' => 'TROCA']))));
    }

    public function test_period_filters_by_service_date_and_accepts_inverted_bounds(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'Janeiro', 'maintenance_date' => '2026-01-15']);
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'Março', 'maintenance_date' => '2026-03-10']);
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'Maio', 'maintenance_date' => '2026-05-20']);

        $admin = $this->adminUser();

        $this->assertSame(['Março'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['de' => '2026-02-01', 'ate' => '2026-04-30']))));
        $this->assertSame(['Março'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['de' => '2026-04-30', 'ate' => '2026-02-01']))));
        $this->assertSame(['Maio', 'Março'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['de' => '2026-03-10']))));
        $this->assertCount(3, $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['de' => '31/12/2025', 'ate' => '2026-02-30']))), 'Data inválida é ignorada.');

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.maintenances.index', ['de' => '2026-02-01', 'ate' => '2026-04-30'])));
        $this->assertSame('2026-02-01', $this->adminElement($xpath, '//input[@name="de"][@type="date"]')->getAttribute('value'));
        $this->assertSame('De', $this->adminText($this->adminElement($xpath, '//label[@for="de"]')));
        $this->assertStringContainsString('de 01/02/2026 a 30/04/2026', $this->adminElement($xpath, '//*[@data-admin-maintenances-summary]')->getAttribute('data-status-text'));
    }

    public function test_provenance_buttons_count_what_the_other_filters_left(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(2)->for($vehicle)->sealedByWorkshop()->create(['maintenance_type' => 'Revisão']);
        Maintenance::factory()->for($vehicle)->declaredByOwner()->create(['maintenance_type' => 'Revisão']);
        Maintenance::factory()->count(3)->for($vehicle)->declaredByOwner()->create(['maintenance_type' => 'Lavagem']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index', ['q' => 'revisão', 'verified' => '0'])));

        $buttons = $this->adminElements($xpath, '//*[@role="group"][@aria-label="Filtrar por procedência"]/button');
        $this->assertSame(['Todas 3', 'Selo da oficina 2', 'Declaradas 1'], array_map(fn ($button): string => $this->adminText($button), $buttons));
        $this->assertSame('true', $buttons[2]->getAttribute('aria-pressed'));
        $this->assertSame(['Revisão'], $this->servicesFrom($xpath));

        $form = $this->adminElement($xpath, '//form[@data-admin-maintenances-form]');
        $this->assertSame('GET', $form->getAttribute('method'));
        $hiddenVerified = $this->adminElement($xpath, './/input[@type="hidden"][@data-admin-maintenances-verified]', $form);
        $this->assertSame('0', $hiddenVerified->getAttribute('value'));
        $this->assertFalse($hiddenVerified->hasAttribute('disabled'));
        $this->assertSame('revisão', $this->adminElement($xpath, './/input[@name="q"]', $form)->getAttribute('value'));

        $all = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index')));
        $this->assertTrue($this->adminElement($all, '//input[@data-admin-maintenances-verified]')->hasAttribute('disabled'), 'Sem procedência escolhida, o campo oculto não vai na URL.');
    }

    public function test_context_filters_come_from_other_screens_and_can_be_removed(): void
    {
        $owner = User::factory()->asUser()->create(['name' => 'Rita']);
        $vehicle = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Uno', 'license_plate' => 'UNO1A11']);
        $workshop = Workshop::factory()->create(['name' => 'Oficina Leste']);
        Maintenance::factory()->for($vehicle)->for($owner)->create(['maintenance_type' => 'Da Rita']);
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'De outra conta']);
        Maintenance::factory()->create(['maintenance_type' => 'Da oficina', 'workshop_id' => $workshop->id]);

        $admin = $this->adminUser();

        $xpath = $this->adminPage($this->actingAs($admin)->get(route('admin.maintenances.index', ['usuario' => $owner->id, 'q' => 'da'])));
        $this->assertSame(['Da Rita'], $this->servicesFrom($xpath));
        $chip = $this->adminElement($xpath, '//ul[@data-admin-maintenances-context]//a');
        $this->assertStringContainsString('Lançadas por Rita', $this->adminText($chip));
        $this->assertSame(route('admin.maintenances.index', ['q' => 'da']), $chip->getAttribute('href'));
        $this->assertSame((string) $owner->id, $this->adminElement($xpath, '//form[@data-admin-maintenances-form]//input[@type="hidden"][@name="usuario"]')->getAttribute('value'));

        $this->assertEqualsCanonicalizing(['Da Rita', 'De outra conta'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['veiculo' => $vehicle->id]))));
        $this->assertSame(['Da oficina'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['oficina' => $workshop->id]))));
        $this->assertCount(3, $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['usuario' => 'abc']))), 'Id inválido é ignorado.');
    }

    public function test_rows_link_the_vehicle_workshop_and_account(): void
    {
        $owner = User::factory()->asUser()->create(['name' => 'Marcos']);
        $vehicle = Vehicle::factory()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Oeste']);
        Maintenance::factory()->for($vehicle)->for($owner)->create(['workshop_id' => $workshop->id, 'kilometers' => 45200]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index')));
        $row = $this->adminElement($xpath, '//tbody/tr[@data-maintenance-row]');

        $this->assertSame(route('admin.vehicles.show', $vehicle), $this->adminElement($xpath, './td[@data-label="Veículo"]//a', $row)->getAttribute('href'));
        $this->assertSame(route('admin.workshops.index', ['q' => 'Oficina Oeste']), $this->adminElement($xpath, './td[@data-label="Oficina"]//a', $row)->getAttribute('href'));
        $this->assertSame(route('admin.users.show', $owner), $this->adminElement($xpath, './td[@data-label="Registrada por"]//a', $row)->getAttribute('href'));
        $this->assertStringContainsString('45.200 km', $this->adminText($this->adminElement($xpath, './th[@scope="row"]', $row)));
        $this->assertSame('descending', $this->adminElement($xpath, '//thead//th[.//a[contains(., "Data")]]')->getAttribute('aria-sort'));
    }

    public function test_date_column_sorts_oldest_first_on_request(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'Antiga', 'maintenance_date' => '2025-01-01']);
        Maintenance::factory()->for($vehicle)->create(['maintenance_type' => 'Recente', 'maintenance_date' => '2026-01-01']);

        $admin = $this->adminUser();

        $this->assertSame(['Recente', 'Antiga'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index'))));
        $this->assertSame(['Antiga', 'Recente'], $this->services($this->actingAs($admin)->get(route('admin.maintenances.index', ['ordenar' => 'data', 'direcao' => 'asc']))));
    }

    public function test_empty_search_names_the_term_and_offers_to_clear(): void
    {
        Maintenance::factory()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.maintenances.index', ['q' => 'motor v12'])));

        $empty = $this->adminElement($xpath, '//tr[@data-slot="table-empty"]');
        $this->assertStringContainsString('Nenhuma manutenção para “motor v12”', $this->adminText($empty));
        $this->assertSame(route('admin.maintenances.index'), $this->adminElement($xpath, './/a[contains(., "Limpar filtros")]', $empty)->getAttribute('href'));
        $this->assertStringContainsString('busca: “motor v12”', $this->adminElement($xpath, '//*[@data-admin-maintenances-summary]')->getAttribute('data-status-text'));
    }

    public function test_filter_script_keeps_the_other_filters_when_switching_provenance(): void
    {
        $script = file_get_contents(resource_path('js/admin-maintenances-filters.js'));

        $this->assertStringContainsString('buildFilterUrl(currentUrl, verified)', $script);
        $this->assertStringContainsString('event.preventDefault();', $script);
        $this->assertStringContainsString('[data-admin-maintenances-verified]', $script);
        $this->assertStringContainsString('[data-slot="table-sort"]', $script);
    }

    /**
     * @return list<string>
     */
    private function services(\Illuminate\Testing\TestResponse $response): array
    {
        return $this->servicesFrom($this->adminPage($response));
    }

    /**
     * @return list<string>
     */
    private function servicesFrom(\DOMXPath $xpath): array
    {
        return array_map(
            fn ($cell): string => trim((string) preg_replace('/\s+/u', ' ', $cell->firstChild?->textContent ?? '')),
            $this->adminElements($xpath, '//tbody/tr[@data-maintenance-row]/th[@scope="row"]'),
        );
    }
}
