<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\VehiclePdfExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * Início do proprietário renderizado no servidor (App\Services\User\OwnerDashboard): primeiro uso
 * com os primeiros passos, KPIs com totais reais, próxima revisão e pendências acionáveis.
 */
class OwnerDashboardTest extends TestCase
{
    use InspectsOwnerPages;
    use RefreshDatabase;

    public function test_first_use_shows_the_checklist_and_hides_the_zero_kpis(): void
    {
        $owner = $this->owner(['name' => 'Marina Souza']);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $this->assertOnlyHeading($xpath, 'Olá, Marina');
        $this->assertSame(0, $xpath->query('//*[@data-stat]')->length, 'Sem veículo, os KPIs (todos zero) ficam de fora.');
        $this->assertSame(0, $xpath->query('//*[@data-owner-pending]')->length);

        $checklist = $this->ownerElement($xpath, '//*[@data-first-steps]');
        $this->assertStringContainsString('0 de 3 concluídos', $this->ownerText($checklist));
        $this->assertSame(['veiculo', 'manutencao', 'pdf'], array_map(
            fn (\DOMElement $step): string => $step->getAttribute('data-first-step'),
            $this->ownerElements($xpath, '//*[@data-first-step]', $checklist),
        ));

        $firstAction = $this->ownerElement($xpath, './/*[@data-first-step="veiculo"]//a[@data-slot="button"]', $checklist);
        $this->assertSame(route('user.vehicles.create'), $firstAction->getAttribute('href'));
        $this->assertSame('primary', $firstAction->getAttribute('data-variant'));

        // O primário da página é "Adicionar veículo"; sem veículo não há "Registrar manutenção" no topo.
        $this->assertNotNull($this->ownerButton($xpath, 'Adicionar veículo'));
        $this->assertSame(0, $xpath->query('//header[@data-slot="page-header"]//a[@href="'.route('user.maintenances.create').'"]')->length);
        $this->assertStringNotContainsString('Carregando', $this->ownerText($this->ownerElement($xpath, '//main')));
    }

    public function test_kpis_are_real_totals_with_the_provenance_split(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23']);

        Maintenance::factory()->count(4)->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'kilometers' => 10_000,
        ]);
        Maintenance::factory()->count(2)->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'kilometers' => 12_000,
        ]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $vehiclesStat = $this->ownerElement($xpath, '//*[@data-stat="vehicles"]');
        $this->assertSame('1', $this->ownerText($this->ownerElement($xpath, './/*[@data-slot="stat-value"]', $vehiclesStat)));
        $this->assertSame(route('user.vehicles.index'), $vehiclesStat->getAttribute('href'));

        $maintenancesStat = $this->ownerElement($xpath, '//*[@data-stat="maintenances"]');
        $this->assertSame('6', $this->ownerText($this->ownerElement($xpath, './/*[@data-slot="stat-value"]', $maintenancesStat)), 'O KPI é o total, não o tamanho da lista de 5.');
        $this->assertSame('2 com selo · 4 declaradas', $this->ownerText($this->ownerElement($xpath, './/*[@data-slot="stat-hint"]', $maintenancesStat)));

        // Últimas manutenções: no máximo 5, com o veículo em cada linha.
        $recent = $this->ownerElements($xpath, '//section[@id="inicio-manutencoes"]//*[@data-maintenance-card]');
        $this->assertCount(5, $recent);
        $this->assertStringContainsString('Honda Civic', $this->ownerText($recent[0]));

        // Meus veículos: miniatura, link para a ficha e os pontos de procedência.
        $vehicleRow = $this->ownerElement($xpath, '//*[@data-owner-vehicles]/li[@data-vehicle-id="'.$vehicle->id.'"]');
        $this->ownerElement($xpath, './/*[@data-vehicle-cover="thumb"]', $vehicleRow);
        $this->assertSame(route('user.vehicles.show', $vehicle), $this->ownerElement($xpath, './/h3/a', $vehicleRow)->getAttribute('href'));
        $this->assertStringContainsString('2 com selo', $this->ownerText($vehicleRow));

        $checklist = $this->ownerElement($xpath, '//*[@data-first-steps]');
        $this->assertStringContainsString('2 de 3 concluídos', $this->ownerText($checklist));
        $this->assertSame('true', $this->ownerElement($xpath, '//*[@data-first-step="manutencao"]')->getAttribute('data-done'));
        $this->assertSame('false', $this->ownerElement($xpath, '//*[@data-first-step="pdf"]')->getAttribute('data-done'));
        $this->assertSame(
            route('user.vehicles.show', $vehicle),
            $this->ownerElement($xpath, '//*[@data-first-step="pdf"]//a')->getAttribute('href'),
        );
    }

    public function test_checklist_disappears_after_the_first_pdf_export(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner);
        Maintenance::factory()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);
        VehiclePdfExport::factory()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $this->assertSame(0, $xpath->query('//*[@data-first-steps]')->length);
        $this->ownerElement($xpath, '//*[@data-stat="vehicles"]');
    }

    public function test_next_revision_is_the_closest_one_among_the_vehicles(): void
    {
        $owner = $this->owner();
        // Revisão a cada 10.000 km a partir do cadastro: o Gol está a 1.000 km dela, o Onix a 8.000.
        $this->ownedVehicle($owner, ['brand' => 'Chevrolet', 'model' => 'Onix', 'odometer_at_registration' => 20_000, 'current_kilometers' => 22_000]);
        $gol = $this->ownedVehicle($owner, ['brand' => 'Volkswagen', 'model' => 'Gol', 'odometer_at_registration' => 50_000, 'current_kilometers' => 59_000]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $stat = $this->ownerElement($xpath, '//*[@data-stat="next-revision"]');
        $this->assertSame('Aos 60.000 km', $this->ownerText($this->ownerElement($xpath, './/*[@data-slot="stat-value"]', $stat)));
        $this->assertSame('Volkswagen Gol: faltam 1.000 km', $this->ownerText($this->ownerElement($xpath, './/*[@data-slot="stat-hint"]', $stat)));
        $this->assertSame(route('user.vehicles.show', $gol), $stat->getAttribute('href'));
        $this->ownerElement($xpath, './/*[@role="progressbar"][@aria-valuetext="Volkswagen Gol: faltam 1.000 km"]', $stat);

        // A revisão perto (dentro do aviso de 2.000 km) vira pendência com "Registrar manutenção".
        $pending = $this->ownerElement($xpath, '//*[@data-pending="revisao-'.$gol->id.'"]');
        $this->assertStringContainsString('Revisão do Volkswagen Gol em 1.000 km', $this->ownerText($pending));
        $this->assertSame(
            route('user.maintenances.create', ['vehicle_id' => $gol->id]),
            $this->ownerElement($xpath, './/a[@data-slot="button"]', $pending)->getAttribute('href'),
        );
    }

    public function test_pending_items_point_to_the_screen_that_solves_them(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, [
            'brand' => 'Fiat',
            'model' => 'Uno',
            'chassis' => null,
            'odometer_at_registration' => 30_000,
            'current_kilometers' => 30_000,
        ]);

        $soon = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Troca da bateria',
            'kilometers' => 30_000,
            'maintenance_date' => today()->subDays(18),
        ]);
        // A garantia vale da data do serviço mais o prazo: 18 dias atrás + 30 dias = daqui a 12 dias.
        MaintenanceWarranty::factory()->forMaintenance($soon)->create(['duration_days' => 30]);

        $later = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'kilometers' => 30_000,
            'maintenance_date' => today()->subDays(30),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($later)->create(['duration_days' => 90]);

        // Garantia de outro dono não aparece.
        $stranger = $this->owner();
        $strangerVehicle = $this->ownedVehicle($stranger);
        $strangerMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $strangerVehicle->id,
            'user_id' => $stranger->id,
            'tenant_id' => $stranger->tenant_id,
            'maintenance_date' => today()->subDays(25),
        ]);
        $strangerWarranty = MaintenanceWarranty::factory()->forMaintenance($strangerMaintenance)->create(['duration_days' => 30]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $chassis = $this->ownerElement($xpath, '//*[@data-pending="chassi-'.$vehicle->id.'"]');
        $this->assertStringContainsString('Informe o chassi do Fiat Uno', $this->ownerText($chassis));
        $this->assertSame(route('user.vehicles.edit', $vehicle).'#dados', $this->ownerElement($xpath, './/a', $chassis)->getAttribute('href'));

        $cover = $this->ownerElement($xpath, '//*[@data-pending="capa-'.$vehicle->id.'"]');
        $this->assertSame(route('user.vehicles.edit', $vehicle).'#capas', $this->ownerElement($xpath, './/a', $cover)->getAttribute('href'));

        $warrantyItems = $this->ownerElements($xpath, '//*[starts-with(@data-pending, "garantia-")]');
        $this->assertCount(1, $warrantyItems, 'Só a garantia deste dono que vence em até 30 dias.');
        $this->assertStringContainsString('Garantia de Troca da bateria vence em 12 dias', $this->ownerText($warrantyItems[0]));
        $this->assertSame(route('user.maintenances.show', $soon), $this->ownerElement($xpath, './/a', $warrantyItems[0])->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//*[@data-pending="garantia-'.$strangerWarranty->id.'"]')->length);
    }

    public function test_nothing_pending_shows_an_all_clear_state(): void
    {
        $owner = $this->owner();
        $this->ownedVehicle($owner, [
            'cover_photo_path' => 'vehicle-covers/capa.jpg',
            'odometer_at_registration' => 10_000,
            'current_kilometers' => 10_000,
        ]);

        $xpath = $this->ownerPage($this->actingAs($owner)->get(route('user.dashboard')));

        $pending = $this->ownerElement($xpath, '//*[@data-owner-pending]');
        $this->assertStringContainsString('Tudo em dia', $this->ownerText($pending));
        $this->assertSame(0, $xpath->query('.//*[@data-pending]', $pending)->length);
    }

    public function test_dashboard_is_only_for_owner_accounts(): void
    {
        $this->get(route('user.dashboard'))->assertRedirect();
    }
}
