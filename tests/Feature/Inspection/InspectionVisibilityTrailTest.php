<?php

namespace Tests\Feature\Inspection;

use App\Enums\VisibilityEventAction;
use App\Models\InspectionVisibilityEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Services\Inspection\InspectionPublicVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A trilha de supressão de um laudo: uma linha por supressão e por devolução, com ator, motivo e
 * horário.
 *
 * O defeito que ela fecha: as três colunas de estado (`hidden_from_public_at`, `hidden_by_user_id`,
 * `hidden_reason`) são um interruptor, e devolver o laudo ao histórico zerava as três. O docblock do
 * serviço exige um humano responsável pela supressão — e era exatamente esse rastro que republicar
 * apagava. Depois disso ninguém descobriria que o laudo esteve fora do ar, quem o tirou nem com que
 * justificativa, num produto cujo valor é procedência.
 *
 * O irmão da OS tem o teste espelho em
 * tests/Feature/WorkshopRecords/MaintenanceVisibilityTrailTest.php.
 */
class InspectionVisibilityTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_suppressing_records_the_event_with_the_actor_and_the_reason(): void
    {
        $admin = $this->admin();
        $inspection = $this->inspection();

        $this->service()->hide($inspection, $admin, 'Laudo emitido para outro chassi.');

        $events = $inspection->fresh()->visibilityEvents;

        $this->assertCount(1, $events);
        $this->assertSame(VisibilityEventAction::Hidden, $events->first()->action);
        $this->assertSame($admin->id, $events->first()->actor_user_id);
        $this->assertSame('Laudo emitido para outro chassi.', $events->first()->reason);
        $this->assertNotNull($events->first()->occurred_at);
    }

    /**
     * O ponto do defeito: devolver acrescenta à trilha em vez de apagá-la. As colunas de estado
     * voltam a ser nulas — elas descrevem a supressão corrente — e o evento anterior fica.
     */
    public function test_restoring_records_its_own_event_and_never_erases_the_previous_one(): void
    {
        $admin = $this->admin();
        $restorer = $this->admin('Outro Admin');
        $inspection = $this->inspection();

        $this->service()->hide($inspection, $admin, 'Fraude confirmada.');
        $this->service()->restore($inspection, $restorer);

        $inspection->refresh();

        // O estado corrente volta a ser "não suprimido"...
        $this->assertNull($inspection->hidden_from_public_at);
        $this->assertNull($inspection->hidden_by_user_id);
        $this->assertNull($inspection->hidden_reason);

        // ...e a trilha sabe que a supressão existiu, com nome e motivo.
        $events = $inspection->visibilityEvents;

        $this->assertCount(2, $events);
        $this->assertSame(VisibilityEventAction::Hidden, $events[0]->action);
        $this->assertSame($admin->id, $events[0]->actor_user_id);
        $this->assertSame('Fraude confirmada.', $events[0]->reason);
        $this->assertSame(VisibilityEventAction::Restored, $events[1]->action);
        $this->assertSame($restorer->id, $events[1]->actor_user_id);
    }

    /**
     * O que a opção de colunas extras (`restored_at`, `restored_by_user_id`) não guardaria: o
     * segundo ciclo sobrescreveria o primeiro. A trilha mantém os três atos, em ordem.
     */
    public function test_hide_restore_hide_keeps_all_three_acts_in_order(): void
    {
        $first = $this->admin('Primeiro Admin');
        $second = $this->admin('Segundo Admin');
        $inspection = $this->inspection();

        $service = $this->service();
        $service->hide($inspection, $first, 'Primeiro motivo.');
        $service->restore($inspection, $first);
        $service->hide($inspection, $second, 'Segundo motivo.');

        $events = $inspection->fresh()->visibilityEvents;

        $this->assertCount(3, $events);

        $this->assertSame(
            [
                VisibilityEventAction::Hidden,
                VisibilityEventAction::Restored,
                VisibilityEventAction::Hidden,
            ],
            $events->pluck('action')->all(),
        );

        $this->assertSame('Primeiro motivo.', $events[0]->reason);
        $this->assertSame($first->id, $events[0]->actor_user_id);
        $this->assertNull($events[1]->reason);
        $this->assertSame('Segundo motivo.', $events[2]->reason);
        $this->assertSame($second->id, $events[2]->actor_user_id);

        // E o estado corrente é o do último ato.
        $this->assertTrue($inspection->fresh()->isHiddenFromPublic());
        $this->assertSame('Segundo motivo.', $inspection->fresh()->hidden_reason);
    }

    /**
     * Suprimir de novo o que já está suprimido é ato novo — outro admin, outro motivo — e a trilha
     * registra o ato. A data da primeira supressão é preservada: é quando o laudo saiu do ar.
     */
    public function test_suppressing_again_records_the_new_act_and_keeps_the_first_date(): void
    {
        $first = $this->admin('Primeiro Admin');
        $second = $this->admin('Segundo Admin');
        $inspection = $this->inspection();

        $service = $this->service();
        $inspection = $service->hide($inspection, $first, 'Motivo inicial.');
        $hiddenAt = $inspection->hidden_from_public_at;

        $this->travel(2)->minutes();
        $inspection = $service->hide($inspection, $second, 'Motivo revisado.');

        $this->assertSame($hiddenAt->toDateTimeString(), $inspection->hidden_from_public_at->toDateTimeString());
        $this->assertSame($second->id, $inspection->hidden_by_user_id);

        $events = $inspection->visibilityEvents;
        $this->assertCount(2, $events);
        $this->assertSame('Motivo inicial.', $events[0]->reason);
        $this->assertSame('Motivo revisado.', $events[1]->reason);
    }

    /**
     * Devolver um laudo que não estava suprimido não inventa transição: a trilha registra atos que
     * aconteceram, e um "devolvido" sem supressão antes seria ruído na prova.
     */
    public function test_restoring_a_report_that_was_not_suppressed_records_nothing(): void
    {
        $inspection = $this->inspection();

        $this->service()->restore($inspection, $this->admin());

        $this->assertCount(0, $inspection->fresh()->visibilityEvents);
    }

    /**
     * Trilha de auditoria não se corrige nem se apaga: um evento errado é seguido de outro, nunca
     * reescrito. Sem isto a tabela seria só um par de campos com mais linhas.
     */
    public function test_the_trail_is_append_only(): void
    {
        $inspection = $this->inspection();
        $this->service()->hide($inspection, $this->admin(), 'Motivo original.');

        $event = $inspection->fresh()->visibilityEvents->first();

        $this->assertFalse($event->update(['reason' => 'Outro motivo.']));
        $this->assertFalse($event->delete());

        $this->assertSame('Motivo original.', $event->fresh()->reason);
        $this->assertCount(1, $inspection->fresh()->visibilityEvents);
    }

    /**
     * Apagar o laudo leva a trilha dele: a chave estrangeira é de verdade (cascadeOnDelete), e é a
     * razão de serem duas tabelas em vez de uma relação polimórfica.
     */
    public function test_deleting_the_report_takes_its_trail(): void
    {
        $inspection = $this->inspection();
        $this->service()->hide($inspection, $this->admin(), 'Chassi divergente.');

        $this->assertSame(1, InspectionVisibilityEvent::query()->count());

        $inspection->fresh()->delete();

        $this->assertSame(0, InspectionVisibilityEvent::query()->count());
    }

    /**
     * Estado e trilha na mesma transação: trilha que pode divergir do estado é pior que trilha
     * nenhuma, porque passa a ser consultada como prova.
     */
    public function test_the_state_and_the_event_land_together(): void
    {
        $inspection = $this->inspection();
        $admin = $this->admin();

        DB::beginTransaction();

        try {
            $this->service()->hide($inspection, $admin, 'Some junto.');
            DB::rollBack();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->assertNull($inspection->fresh()->hidden_from_public_at);
        $this->assertSame(0, InspectionVisibilityEvent::query()->count());
    }

    /**
     * Supressão sem auditoria visível não serve para nada: a trilha aparece na tela que consulta
     * direto, sem passar pela redação (.ai/rules/admin.md).
     */
    public function test_the_admin_screen_shows_the_whole_trail(): void
    {
        $admin = $this->admin('Equipe RevisaLog');
        $restorer = $this->admin('Fulano da Equipe');
        $inspection = $this->inspection();

        $service = $this->service();
        $service->hide($inspection, $admin, 'Laudo emitido para outro chassi.');
        $service->restore($inspection, $restorer);
        $service->hide($inspection, $admin, 'Fraude confirmada na análise.');

        $this->actingAs($admin)
            ->get(route('admin.inspections.index'))
            ->assertOk()
            ->assertSee('Histórico de supressão')
            ->assertSee('Laudo emitido para outro chassi.')
            ->assertSee('Devolvido ao histórico')
            ->assertSee('Fulano da Equipe')
            ->assertSee('Fraude confirmada na análise.')
            ->assertSee('data-visibility-trail="3"', false);
    }

    /**
     * O rastro é de auditoria interna. Depois de devolver o laudo ao histórico ele volta a aparecer
     * em todas as superfícies — e é aí que o vazamento seria fácil: nem o nome de quem da equipe
     * decidiu nem o motivo podem sair na página pública, na ficha do dono ou na API. O comprador vê
     * o laudo, não a cozinha (.ai/rules/public-lookup.md).
     */
    public function test_the_trail_never_leaks_outside_the_admin(): void
    {
        $admin = $this->admin('Fulano da Equipe');
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
        ]);

        $service = $this->service();
        $service->hide($inspection, $admin, 'Pedido de remoção honrado.');
        $service->restore($inspection, $admin);

        // Página pública do laudo: visitante.
        $this->get(route('inspections.public', $inspection->verification_code))
            ->assertOk()
            ->assertDontSee('Fulano da Equipe')
            ->assertDontSee('Pedido de remoção honrado.')
            ->assertDontSee('Histórico de supressão');

        // Ficha do proprietário.
        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('Fulano da Equipe')
            ->assertDontSee('Pedido de remoção honrado.')
            ->assertDontSee('Histórico de supressão');

        // E a API do app: o laudo ainda não tem recurso, então o que não pode é a trilha aparecer
        // no veículo por tabelar junto (.ai/rules/inspections.md, "O que ainda não existe").
        $this->actingAs($owner->fresh(), 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertDontSee('Fulano da Equipe')
            ->assertDontSee('Pedido de remoção honrado.')
            ->assertDontSee('visibility_events');
    }

    private function service(): InspectionPublicVisibilityService
    {
        return app(InspectionPublicVisibilityService::class);
    }

    private function admin(string $name = 'Equipe RevisaLog'): User
    {
        return User::factory()->asUser()->asAdmin()->create(['name' => $name])->fresh();
    }

    private function inspection(): VehicleInspection
    {
        $vehicle = Vehicle::factory()->create();

        return VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
        ]);
    }
}
