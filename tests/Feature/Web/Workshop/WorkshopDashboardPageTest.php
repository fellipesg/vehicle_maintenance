<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Início da oficina como fila de trabalho: Nova OS pela placa, indicadores com totais reais que
 * abrem a lista filtrada, pendências com a próxima ação e as OS recentes (um link por OS).
 */
class WorkshopDashboardPageTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_new_order_by_plate_is_a_get_form_to_the_new_order_page(): void
    {
        $user = $this->workshopUser();

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $this->assertSingleH1($xpath, 'Olá, '.$user->name);
        $form = $this->element($xpath, '//*[@data-new-order-by-plate]//form');
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('workshop.maintenances.create'), $form->getAttribute('action'));
        $this->assertSame('plate', $this->element($xpath, './/input[@name="license_plate"]', $form)->getAttribute('data-mask'));
    }

    public function test_indicators_use_real_totals_and_open_the_filtered_list(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(10, 0));
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('KPI1A23');
        $thisMonth = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-09-05']);
        $lastMonth = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-08-10']);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'workshop_id' => $user->workshop->id, 'maintenance_date' => '2026-09-10']);
        MaintenanceWarranty::factory()->forMaintenance($thisMonth)->create(['duration_days' => 20]);
        MaintenanceWarranty::factory()->forMaintenance($lastMonth)->create(['duration_days' => 365]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $stat = fn (string $key): \DOMElement => $this->element($xpath, '//*[@data-stat="'.$key.'"]');
        $value = fn (string $key): string => $this->text($this->element($xpath, './/*[@data-slot="stat-value"]', $stat($key)));

        $this->assertSame('1', $value('month'));
        $this->assertSame('2', $value('sealed'));
        $this->assertSame('2', $value('warranties'));
        $this->assertSame('1', $value('expiring'));
        $this->assertSame(route('workshop.maintenances.index', ['verified' => '1', 'de' => '2026-09-01', 'ate' => '2026-09-20']), $stat('month')->getAttribute('href'));
        $this->assertSame(route('workshop.maintenances.index', ['garantia' => 'vencendo']), $stat('expiring')->getAttribute('href'));
    }

    public function test_pending_items_count_and_link_to_the_next_action(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('PEN1A23');
        $withEverything = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        Invoice::factory()->create(['maintenance_id' => $withEverything->id]);
        MaintenancePhoto::factory()->create(['maintenance_id' => $withEverything->id, 'stage' => MaintenancePhoto::STAGE_AFTER]);
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $withoutInvoice = $this->element($xpath, '//*[@data-pending-item="sem-nfe"]');
        $this->assertStringContainsString('2 OS sem nota fiscal', $this->text($withoutInvoice));
        $this->assertSame(route('workshop.maintenances.index', ['verified' => '1', 'anexos' => 'sem-nfe']), $this->element($xpath, './/a', $withoutInvoice)->getAttribute('href'));
        $this->assertStringContainsString('2 OS sem fotos de depois', $this->text($this->element($xpath, '//*[@data-pending-item="sem-fotos-depois"]')));
        $this->assertSame(route('workshop.profile.edit'), $this->element($xpath, '//*[@data-pending-item="sem-logo"]//a')->getAttribute('href'));
        $this->assertSame(route('workshop.warranty-templates.create'), $this->element($xpath, '//*[@data-pending-item="sem-modelo-garantia"]//a')->getAttribute('href'));
    }

    public function test_all_done_shows_the_positive_empty_state(): void
    {
        $user = $this->workshopUser();
        $user->workshop->update(['logo_path' => 'workshop-logos/1.png']);
        WarrantyTemplate::factory()->forWorkshop($user->workshop)->create(['is_active' => true]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-pending-item]'));
        $this->assertStringContainsString('Tudo em dia', $this->text($this->element($xpath, '//*[@data-pending]')));
    }

    public function test_recent_orders_have_one_link_each_and_an_empty_state(): void
    {
        $user = $this->workshopUser();

        $empty = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));
        $this->assertStringContainsString('Nenhuma OS registrada ainda', $this->text($this->element($empty, '//*[@data-recent]')));

        $order = $this->sealedOrder($user, ['vehicle_id' => $this->ownedVehicle('REC1A23')->id]);
        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $recent = $this->element($xpath, '//*[@data-recent]');
        $this->assertSame(1, $this->countNodes($xpath, './/a[@href="'.route('workshop.maintenances.show', $order).'"]', $recent), 'Um link por OS.');
        $this->assertSame(route('workshop.maintenances.index'), $this->element($xpath, './/a[contains(., "Ver todas as OS")]', $recent)->getAttribute('href'));
    }

    public function test_without_workshop_the_empty_state_lists_the_onboarding_steps(): void
    {
        $user = User::factory()->asWorkshop()->create();
        $user->workshop->delete();
        $user->unsetRelation('workshop');

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.dashboard')));

        $empty = $this->element($xpath, '//*[@data-dashboard-empty]');
        $this->assertStringContainsString('Complete o cadastro da sua oficina', $this->text($empty));
        $this->assertSame(3, $this->countNodes($xpath, './/ol/li', $empty));
        $this->assertSame(route('workshop.profile.create'), $this->element($xpath, './/a[contains(., "Cadastrar oficina")]', $empty)->getAttribute('href'));
    }
}
