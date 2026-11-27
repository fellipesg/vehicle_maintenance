<?php

namespace Tests\Feature\WorkshopRecords;

use App\Enums\VisibilityEventAction;
use App\Models\Maintenance;
use App\Models\MaintenanceVisibilityEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Maintenance\MaintenancePublicVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A trilha de supressão de uma OS, espelho de
 * tests/Feature/Inspection/InspectionVisibilityTrailTest.php — os dois serviços são irmãos e a
 * equipe lê um esperando o outro.
 *
 * Mesmo defeito dos dois lados: as colunas de estado são um interruptor e republicar zerava quem
 * ocultou e por quê. Aqui zerar é **obrigatório**, não só coerente:
 * `Maintenance::scopeCountsTowardOdometer` lê `hidden_by_user_id` para distinguir a supressão da
 * equipe do ocultar antigo do proprietário, então o nome não pode ficar preso na coluna depois da
 * devolução — e é justamente por isso que o histórico precisa de tabela própria.
 */
class MaintenanceVisibilityTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_hiding_records_the_event_with_the_actor_and_the_reason(): void
    {
        $admin = $this->admin();
        $maintenance = $this->maintenance();

        $this->service()->hide($maintenance, $admin, 'Chassi de outro veículo.');

        $events = $maintenance->fresh()->visibilityEvents;

        $this->assertCount(1, $events);
        $this->assertSame(VisibilityEventAction::Hidden, $events->first()->action);
        $this->assertSame($admin->id, $events->first()->actor_user_id);
        $this->assertSame('Chassi de outro veículo.', $events->first()->reason);
        $this->assertNotNull($events->first()->occurred_at);
    }

    public function test_restoring_records_its_own_event_and_never_erases_the_previous_one(): void
    {
        $admin = $this->admin();
        $restorer = $this->admin('Outro Admin');
        $maintenance = $this->maintenance();

        $this->service()->hide($maintenance, $admin, 'Fraude confirmada.');
        $this->service()->restore($maintenance, $restorer);

        $maintenance->refresh();

        $this->assertNull($maintenance->hidden_from_public_at);
        $this->assertNull($maintenance->hidden_by_user_id);
        $this->assertNull($maintenance->hidden_reason);

        $events = $maintenance->visibilityEvents;

        $this->assertCount(2, $events);
        $this->assertSame(VisibilityEventAction::Hidden, $events[0]->action);
        $this->assertSame('Fraude confirmada.', $events[0]->reason);
        $this->assertSame($admin->id, $events[0]->actor_user_id);
        $this->assertSame(VisibilityEventAction::Restored, $events[1]->action);
        $this->assertSame($restorer->id, $events[1]->actor_user_id);
    }

    public function test_hide_restore_hide_keeps_all_three_acts_in_order(): void
    {
        $first = $this->admin('Primeiro Admin');
        $second = $this->admin('Segundo Admin');
        $maintenance = $this->maintenance();

        $service = $this->service();
        $service->hide($maintenance, $first, 'Primeiro motivo.');
        $service->restore($maintenance, $first);
        $service->hide($maintenance, $second, 'Segundo motivo.');

        $events = $maintenance->fresh()->visibilityEvents;

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
        $this->assertNull($events[1]->reason);
        $this->assertSame('Segundo motivo.', $events[2]->reason);
    }

    /**
     * A assimetria legítima com o irmão do laudo, guardada aqui para ninguém "consertá-la": a OS
     * suprimida sai da conta do hodômetro e o `current_kilometers` é recalculado na hora, nos dois
     * sentidos. A trilha não mudou isso.
     */
    public function test_the_trail_did_not_cost_the_odometer_recalculation(): void
    {
        $admin = $this->admin();

        // odometer_at_registration entra no max() do VehicleMileageService, e o factory o sorteia:
        // sem fixar em 0 o piso do veículo mascararia o efeito da supressão. O ano é fixo porque o
        // teto de plausibilidade sai dele (.ai/rules/models.md).
        $vehicle = Vehicle::factory()->create([
            'year' => now()->year - 6,
            'odometer_at_registration' => 0,
            'current_kilometers' => 0,
        ]);

        $this->maintenance($vehicle, ['kilometers' => 30_000, 'maintenance_date' => now()->subYear()->toDateString()]);
        $high = $this->maintenance($vehicle, ['kilometers' => 90_000, 'maintenance_date' => now()->toDateString()]);

        app(\App\Services\Vehicle\VehicleMileageService::class)->refreshCurrentKilometers($vehicle);
        $this->assertSame(90_000, $vehicle->fresh()->current_kilometers);

        $high = $this->service()->hide($high, $admin, 'Km digitado errado.');
        $this->assertSame(30_000, $vehicle->fresh()->current_kilometers);

        $this->service()->restore($high, $admin);
        $this->assertSame(90_000, $vehicle->fresh()->current_kilometers);

        // E os dois atos estão na trilha.
        $this->assertCount(2, $high->fresh()->visibilityEvents);
    }

    /**
     * Devolver uma OS que não estava oculta não inventa transição. É também o caso das linhas do
     * fluxo antigo, ocultadas pelo próprio proprietário (`hidden_by_user_id` nulo): a trilha não
     * sabe nada de antes de existir, e não finge saber.
     */
    public function test_restoring_a_record_that_was_not_hidden_records_nothing(): void
    {
        $maintenance = $this->maintenance();

        $this->service()->restore($maintenance, $this->admin());

        $this->assertCount(0, $maintenance->fresh()->visibilityEvents);
    }

    public function test_the_trail_is_append_only(): void
    {
        $maintenance = $this->maintenance();
        $this->service()->hide($maintenance, $this->admin(), 'Motivo original.');

        $event = $maintenance->fresh()->visibilityEvents->first();

        $this->assertFalse($event->update(['reason' => 'Outro motivo.']));
        $this->assertFalse($event->delete());

        $this->assertSame('Motivo original.', $event->fresh()->reason);
    }

    public function test_deleting_the_record_takes_its_trail(): void
    {
        $maintenance = $this->maintenance();
        $this->service()->hide($maintenance, $this->admin(), 'Fraude confirmada.');

        $this->assertSame(1, MaintenanceVisibilityEvent::query()->count());

        $maintenance->fresh()->delete();

        $this->assertSame(0, MaintenanceVisibilityEvent::query()->count());
    }

    /**
     * A mesma exigência do laudo: a trilha aparece na tela que consulta direto.
     */
    public function test_the_admin_screen_shows_the_whole_trail(): void
    {
        $admin = $this->admin('Equipe RevisaLog');
        $restorer = $this->admin('Fulano da Equipe');
        $maintenance = $this->maintenance();

        $service = $this->service();
        $service->hide($maintenance, $admin, 'Chassi de outro veículo.');
        $service->restore($maintenance, $restorer);
        $service->hide($maintenance, $admin, 'Fraude confirmada na análise.');

        $this->actingAs($admin)
            ->get(route('admin.maintenances.index'))
            ->assertOk()
            ->assertSee('Histórico de supressão')
            ->assertSee('Chassi de outro veículo.')
            ->assertSee('Devolvido ao histórico')
            ->assertSee('Fulano da Equipe')
            ->assertSee('Fraude confirmada na análise.')
            ->assertSee('data-visibility-trail="3"', false);
    }

    /**
     * Depois de devolver a OS à consulta ela volta a aparecer em todas as superfícies, e é aí que o
     * vazamento seria fácil: nem o nome de quem da equipe decidiu nem o motivo saem na busca
     * pública, na ficha do dono ou na API do app.
     */
    public function test_the_trail_never_leaks_outside_the_admin(): void
    {
        $admin = $this->admin('Fulano da Equipe');
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'year' => now()->year - 6,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $maintenance = $this->maintenance($vehicle);

        $service = $this->service();
        $service->hide($maintenance, $admin, 'Pedido de remoção honrado.');
        $service->restore($maintenance, $admin);

        // Busca pública: o comprador, que é outra conta e não tem vínculo com o veículo.
        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'ABC1D23']))
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

        // API do app: MaintenanceResource não ganhou a trilha.
        $this->actingAs($owner->fresh(), 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertDontSee('Fulano da Equipe')
            ->assertDontSee('Pedido de remoção honrado.')
            ->assertDontSee('visibility_events');

        $this->actingAs($owner->fresh(), 'sanctum')
            ->getJson('/api/v1/maintenances')
            ->assertOk()
            ->assertDontSee('Fulano da Equipe')
            ->assertDontSee('Pedido de remoção honrado.')
            ->assertDontSee('visibility_events');
    }

    private function service(): MaintenancePublicVisibilityService
    {
        return app(MaintenancePublicVisibilityService::class);
    }

    private function admin(string $name = 'Equipe RevisaLog'): User
    {
        return User::factory()->asUser()->asAdmin()->create(['name' => $name])->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenance(?Vehicle $vehicle = null, array $attributes = []): Maintenance
    {
        return Maintenance::factory()->create(array_merge([
            'vehicle_id' => ($vehicle ?? Vehicle::factory()->create(['year' => now()->year - 6]))->id,
        ], $attributes));
    }
}
