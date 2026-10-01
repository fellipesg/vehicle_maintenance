<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * Detalhe da OS pelo partial compartilhado (maintenances._detail) e a regra de autoria: a oficina
 * altera e exclui só as OS com o Selo dela. Registros que um proprietário ou lojista declarou
 * citando a oficina ficam só para consulta, com a explicação na página.
 */
class WorkshopMaintenanceDetailPageTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_sealed_order_uses_the_shared_detail_with_header_trail_and_actions(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('DET1A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id, 'maintenance_type' => 'Troca de embreagem', 'maintenance_date' => '2026-04-02']);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $order)));

        $this->assertSingleH1($xpath, 'Troca de embreagem');
        $this->assertSame('Fiat Argo · DET1A23 · 02/04/2026', $this->text($this->element($xpath, '//*[@data-slot="page-header-description"]')));
        $trail = array_map(fn (\DOMElement $item): string => $this->text($item), iterator_to_array($xpath->query('//nav[@aria-label="Trilha"]//li')));
        $this->assertSame(['Ordens de serviço', 'Troca de embreagem · DET1A23'], $trail);

        $this->assertSame(1, $this->countNodes($xpath, '//*[@data-slot="maintenance-detail"]'));
        $this->assertSame(1, $this->countNodes($xpath, '//*[@data-slot="maintenance-warranty"]'));
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-declared-notice]'));

        $actions = $this->element($xpath, '//*[@data-slot="page-header-actions"]');
        $this->assertSame(route('workshop.maintenances.edit', $order), $this->element($xpath, './/a[contains(., "Editar OS")]', $actions)->getAttribute('href'));
        $this->assertSame('Mais ações', $this->element($xpath, './/button[@aria-haspopup="menu"]', $actions)->getAttribute('aria-label'));
        $this->assertSame(
            route('workshop.maintenances.create', ['license_plate' => 'DET1A23']),
            $this->element($xpath, './/a[@role="menuitem"][contains(., "Nova OS para este veículo")]', $actions)->getAttribute('href'),
        );
    }

    public function test_delete_asks_in_the_app_dialog_naming_photos_invoices_and_the_code(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('DEL1A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);
        MaintenancePhoto::factory()->count(3)->create(['maintenance_id' => $order->id]);
        Invoice::factory()->create(['maintenance_id' => $order->id]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $order)));

        $form = $this->element($xpath, '//form[@action="'.route('workshop.maintenances.destroy', $order).'"][.//input[@name="_method"][@value="DELETE"]]');
        $button = $this->element($xpath, './/button[@type="submit"][@role="menuitem"]', $form);

        $this->assertSame('Excluir OS', $this->text($button));
        $this->assertSame('Excluir a OS de DEL1A23?', $button->getAttribute('data-confirm-title'));
        $this->assertSame(
            'A OS sai do histórico do veículo DEL1A23. Os anexos também são apagados: 3 fotos e 1 nota fiscal. O código '.$order->verification_code.' deixa de valer e o proprietário perde este registro com Selo da oficina. Não é possível desfazer.',
            $button->getAttribute('data-confirm'),
        );
        $this->assertSame('danger', $button->getAttribute('data-confirm-variant'));
        $this->assertSame('Excluir OS', $button->getAttribute('data-confirm-action-label'));
    }

    public function test_updated_after_the_seal_shows_the_update_date(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('UPD1A23');
        $order = $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);

        $page = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $order)));
        $this->assertSame(0, $this->countNodes($page, '//*[@data-updated-after-seal]'), 'Recém-emitida, sem aviso.');

        // Gravado em UTC, mostrado no horário de Brasília (o mesmo do /v/{código} e do PDF).
        $localUpdate = now('America/Sao_Paulo')->subDay()->setTime(14, 30);
        $order->forceFill(['verified_at' => now()->subDays(3), 'updated_at' => $localUpdate->copy()->utc()])->saveQuietly();

        $page = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $order)));
        $this->assertStringContainsString(
            'Atualizada em '.$localUpdate->format('d/m/Y').' às 14:30, depois da emissão do selo.',
            $this->text($this->element($page, '//*[@data-updated-after-seal]')),
        );
    }

    public function test_declared_record_citing_the_workshop_is_read_only(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('DCL1A23');
        $declared = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $user->workshop->id,
            'maintenance_type' => 'Revisão declarada',
        ]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.show', $declared)));

        $this->assertSingleH1($xpath, 'Revisão declarada');
        $this->assertStringContainsString('Declarada pelo proprietário, sem o Selo da sua oficina', $this->text($this->element($xpath, '//*[@data-declared-notice]')));
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-slot="page-header-actions"]'));
        $this->assertSame(0, $this->countNodes($xpath, '//a[@href="'.route('workshop.maintenances.edit', $declared).'"]'));
        $this->assertSame(0, $this->countNodes($xpath, '//form[@action="'.route('workshop.maintenances.destroy', $declared).'"]'));

        $this->actingAs($user)->get(route('workshop.maintenances.edit', $declared))
            ->assertRedirect(route('workshop.maintenances.show', $declared))
            ->assertSessionHas('error');

        $this->actingAs($user)->put(route('workshop.maintenances.update', $declared), [
            'maintenance_type' => 'Alterada pela oficina',
            'maintenance_date' => '2026-03-10',
            'kilometers' => 40000,
            'service_category' => 'mechanical',
        ])->assertRedirect(route('workshop.maintenances.show', $declared));

        $this->actingAs($user)->delete(route('workshop.maintenances.destroy', $declared))
            ->assertRedirect(route('workshop.maintenances.show', $declared));

        $this->assertDatabaseHas('maintenances', ['id' => $declared->id, 'maintenance_type' => 'Revisão declarada']);
    }

    public function test_other_workshop_cannot_open_the_order(): void
    {
        $owner = $this->workshopUser();
        $order = $this->sealedOrder($owner, ['vehicle_id' => $this->ownedVehicle('OUT1A23')->id]);

        $this->actingAs($this->workshopUser())->get(route('workshop.maintenances.show', $order))->assertForbidden();
    }
}
