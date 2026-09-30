<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Lista "Ordens de serviço": filtros em GET (placa, período, categoria, anexos, garantia e
 * procedência), tabela Data · Veículo · Serviço · Itens / Total · Anexos · Código do selo
 * (empilhada no celular), contagens, estados vazios e paginação que mantém os filtros.
 */
class WorkshopMaintenanceListPageTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_table_shows_the_columns_with_counts_totals_and_seal_code(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('LST1A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Revisão 40 mil', 'maintenance_date' => '2026-03-10', 'kilometers' => 40500, 'service_category' => 'mechanical']);
        MaintenanceItem::factory()->create(['maintenance_id' => $order->id, 'quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);
        MaintenanceItem::factory()->create(['maintenance_id' => $order->id, 'quantity' => 1, 'unit_price' => 50.5, 'total_price' => 50.5]);
        Invoice::factory()->create(['maintenance_id' => $order->id]);
        MaintenancePhoto::factory()->count(3)->create(['maintenance_id' => $order->id]);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.index'));
        $xpath = $this->page($response);

        $this->assertSingleH1($xpath, 'Ordens de serviço');

        $table = $this->element($xpath, '//*[@data-workshop-maintenances-table]//table');
        $headers = array_map(fn (\DOMElement $th): string => $this->text($th), iterator_to_array($xpath->query('.//thead//th', $table)));
        $this->assertSame('Data', trim(preg_replace('/,.*$/', '', $headers[0])));
        $this->assertSame(['Veículo', 'Serviço', 'Itens / Total', 'Anexos', 'Código do selo'], array_slice($headers, 1));

        $row = $this->element($xpath, './/tr[@data-maintenance-row="'.$order->id.'"]', $table);
        $this->assertSame('1', $row->getAttribute('data-verified'));
        $service = $this->element($xpath, './th[@scope="row"]//a', $row);
        $this->assertSame(route('workshop.maintenances.show', $order), $service->getAttribute('href'));
        $this->assertSame('Revisão 40 mil', $this->text($service));

        $rowText = $this->text($row);
        $this->assertStringContainsString('10/03/2026', $rowText);
        $this->assertStringContainsString('40.500 km', $rowText);
        $this->assertStringContainsString('Fiat Argo', $rowText);
        $this->assertStringContainsString('LST1A23', $rowText);
        $this->assertStringContainsString('2 itens', $rowText);
        $this->assertStringContainsString('R$ 250,50', $rowText);
        $this->assertStringContainsString('1 nota fiscal', $rowText);
        $this->assertStringContainsString('3 fotos', $rowText);
        $this->assertStringContainsString($order->verification_code, $rowText);
        $this->assertStringNotContainsString('Registrado por', $rowText);

        // Empilhada abaixo de md, com o rótulo da coluna em cada célula.
        $this->assertSame('md', $this->element($xpath, '//*[@data-workshop-maintenances-table]')->getAttribute('data-stack'));
        $this->assertSame('Anexos', $this->element($xpath, './td[4]', $row)->getAttribute('data-label'));

        // Data ordenável, mais recente primeiro.
        $this->assertSame('descending', $this->element($xpath, './/thead//th[1]', $table)->getAttribute('aria-sort'));
    }

    public function test_list_does_not_query_per_row(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('QRY1A23');

        foreach (range(1, 3) as $day) {
            $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => "2026-03-0{$day}"]);
            Invoice::factory()->create(['maintenance_id' => $order->id]);
        }

        $this->actingAs($user)->get(route('workshop.maintenances.index'))->assertOk();
        $threeRows = $this->countQueries(fn () => $this->actingAs($user)->get(route('workshop.maintenances.index'))->assertOk());

        foreach (range(4, 9) as $day) {
            $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => "2026-03-0{$day}"]);
            Invoice::factory()->create(['maintenance_id' => $order->id]);
        }

        $nineRows = $this->countQueries(fn () => $this->actingAs($user)->get(route('workshop.maintenances.index'))->assertOk());

        $this->assertSame($threeRows, $nineRows, 'A contagem de consultas não cresce com as linhas.');
    }

    public function test_filters_by_plate_period_category_attachments_and_warranty(): void
    {
        $user = $this->workshopUser();
        $argo = $this->ownedVehicle('ARG1A23');
        $onix = $this->ownedVehicle('ONX9B87', ['brand' => 'Chevrolet', 'model' => 'Onix']);

        $withInvoice = $this->sealedOrder($user, ['vehicle_id' => $argo->id, 'maintenance_type' => 'Com nota', 'maintenance_date' => '2026-01-15', 'service_category' => 'electrical']);
        Invoice::factory()->create(['maintenance_id' => $withInvoice->id]);
        MaintenancePhoto::factory()->create(['maintenance_id' => $withInvoice->id, 'stage' => MaintenancePhoto::STAGE_AFTER]);
        $withoutInvoice = $this->sealedOrder($user, ['vehicle_id' => $onix->id, 'maintenance_type' => 'Sem nota', 'maintenance_date' => '2026-02-20', 'service_category' => 'mechanical']);
        $warranty = $this->sealedOrder($user, ['vehicle_id' => $onix->id, 'maintenance_type' => 'Com garantia', 'maintenance_date' => now()->subDays(10)->toDateString(), 'service_category' => 'mechanical']);
        MaintenanceWarranty::factory()->forMaintenance($warranty)->create(['duration_days' => 30]);

        $titles = function (array $query) use ($user): array {
            $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index', $query)));

            return array_map(fn (\DOMElement $link): string => $this->text($link), iterator_to_array($xpath->query('//tr[@data-maintenance-row]/th//a')));
        };

        $this->assertSame(['Com nota'], $titles(['placa' => 'arg1']));
        $this->assertSame(['Sem nota'], $titles(['de' => '2026-02-01', 'ate' => '2026-02-28']));
        $this->assertSame(['Com nota'], $titles(['categoria' => 'electrical']));
        $this->assertSame(['Com nota'], $titles(['anexos' => 'com-nfe']));
        $this->assertEqualsCanonicalizing(['Sem nota', 'Com garantia'], $titles(['anexos' => 'sem-nfe']));
        $this->assertEqualsCanonicalizing(['Sem nota', 'Com garantia'], $titles(['anexos' => 'sem-fotos-depois']));
        $this->assertSame(['Com garantia'], $titles(['garantia' => 'vigente']));
        $this->assertSame(['Com garantia'], $titles(['garantia' => 'vencendo']));
        $this->assertSame(['Com garantia', 'Sem nota', 'Com nota'], $titles([]), 'Mais recente primeiro.');
        $this->assertSame(['Com nota', 'Sem nota', 'Com garantia'], $titles(['ordenar' => 'data', 'direcao' => 'asc']));

        // Filtro inválido é ignorado.
        $this->assertCount(3, $titles(['categoria' => 'foguete', 'de' => '2026-13-45', 'anexos' => 'x']));
    }

    public function test_filter_panel_keeps_values_and_counts_active_filters(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index', ['placa' => 'abc', 'categoria' => 'painting', 'verified' => '1'])));

        $panel = $this->element($xpath, '//details[@data-maintenance-filters]');
        $this->assertTrue($panel->hasAttribute('open'));
        $this->assertStringContainsString('2 ativos', $this->text($this->element($xpath, './summary', $panel)));

        $form = $this->element($xpath, './/form', $panel);
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame('ABC', $this->element($xpath, './/input[@name="placa"]', $form)->getAttribute('value'));
        $this->assertTrue($this->element($xpath, './/select[@name="categoria"]/option[@value="painting"]', $form)->hasAttribute('selected'));
        $this->assertSame('1', $this->element($xpath, './/input[@type="hidden"][@name="verified"]', $form)->getAttribute('value'));
        $this->assertSame(route('workshop.maintenances.index').'?verified=1', $this->element($xpath, './/a[normalize-space()="Limpar filtros"]', $form)->getAttribute('href'));
    }

    public function test_provenance_control_counts_sealed_and_declared(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('PRV1A23');
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'workshop_id' => $user->workshop->id, 'maintenance_type' => 'Declarada citando']);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index', ['verified' => '0'])));

        $control = $this->element($xpath, '//nav[@aria-label="Filtrar por procedência"]');
        $this->assertSame(['Todas 3', 'Selo da oficina 2', 'Declaradas 1'], array_map(fn (\DOMElement $link): string => $this->text($link), iterator_to_array($xpath->query('.//a', $control))));
        $this->assertSame('page', $this->element($xpath, './/a[contains(., "Declaradas")]', $control)->getAttribute('aria-current'));

        $row = $this->element($xpath, '//tr[@data-maintenance-row]');
        $this->assertSame('0', $row->getAttribute('data-verified'));
        $this->assertStringContainsString('Declarada pelo proprietário', $this->text($row));
        $this->assertStringContainsString('Sem selo', $this->text($row));
        $this->assertStringContainsString('1 OS neste filtro', $this->text($this->element($xpath, '//*[@data-maintenances-total]')));
    }

    public function test_empty_states_for_no_orders_and_for_filters(): void
    {
        $user = $this->workshopUser();

        $empty = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index')));
        $all = $this->element($empty, '//*[@data-maintenances-empty="all"]');
        $this->assertStringContainsString('Nenhuma OS registrada ainda', $this->text($all));
        $this->assertSame(route('workshop.maintenances.create'), $this->element($empty, './/a', $all)->getAttribute('href'));

        $filtered = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index', ['placa' => 'xyz'])));
        $box = $this->element($filtered, '//*[@data-maintenances-empty="filtered"]');
        $this->assertStringContainsString('Nenhuma OS neste filtro', $this->text($box));
        $this->assertSame(route('workshop.maintenances.index'), $this->element($filtered, './/a[normalize-space()="Limpar filtros"]', $box)->getAttribute('href'));
    }

    public function test_pagination_keeps_the_filters(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('PAG1A23');
        Maintenance::factory()->count(16)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'workshop_id' => $user->workshop->id, 'service_category' => 'mechanical']);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.index', ['categoria' => 'mechanical']));

        $response->assertOk()->assertSee('categoria=mechanical&amp;page=2', false);
    }

    public function test_workshop_without_profile_sees_the_empty_state(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();
        $user->unsetRelation('workshop');

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.index')));

        $this->assertSingleH1($xpath, 'Ordens de serviço');
        $this->assertStringContainsString('Cadastre sua oficina para ver as OS', $this->text($this->element($xpath, '//*[@data-slot="empty-state"]')));
        $this->assertSame(0, $this->countNodes($xpath, '//details[@data-maintenance-filters]'));
    }
}
