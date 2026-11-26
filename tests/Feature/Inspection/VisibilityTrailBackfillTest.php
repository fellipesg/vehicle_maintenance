<?php

namespace Tests\Feature\Inspection;

use App\Enums\VisibilityEventAction;
use App\Models\InspectionVisibilityEvent;
use App\Models\Maintenance;
use App\Models\MaintenanceVisibilityEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O backfill da migração: o que já está suprimido hoje ganha o evento da supressão que o deixou
 * assim, para o histórico não começar vazio onde já existe estado.
 *
 * A migração roda no Neon com dados reais, então o teste a executa de verdade — `down()` e `up()`
 * sobre linhas suprimidas gravadas sem trilha, que é exatamente a forma do banco antes dela.
 */
class VisibilityTrailBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_26_120000_create_visibility_event_tables.php';

    public function test_a_suppressed_report_gets_the_event_that_put_it_there(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create(['name' => 'Equipe RevisaLog']);
        $hiddenAt = now()->subMonths(3)->startOfSecond();

        $inspection = $this->inspection();
        $inspection->forceFill([
            'hidden_from_public_at' => $hiddenAt,
            'hidden_by_user_id' => $admin->id,
            'hidden_reason' => 'Laudo com chassi divergente.',
        ])->saveQuietly();

        $visible = $this->inspection();

        $this->rerunMigration();

        $events = InspectionVisibilityEvent::query()->get();

        $this->assertCount(1, $events, 'Só o laudo suprimido ganha evento.');
        $this->assertSame($inspection->id, $events->first()->vehicle_inspection_id);
        $this->assertSame(VisibilityEventAction::Hidden, $events->first()->action);
        $this->assertSame($admin->id, $events->first()->actor_user_id);
        $this->assertSame('Laudo com chassi divergente.', $events->first()->reason);

        // O evento carrega o horário original da supressão, não o da migração: é quando o ato
        // aconteceu. É por isso que occurred_at existe ao lado de created_at.
        $this->assertSame(
            $hiddenAt->toDateTimeString(),
            $events->first()->occurred_at->toDateTimeString(),
        );
        $this->assertTrue($events->first()->created_at->greaterThan($events->first()->occurred_at));

        $this->assertCount(0, $visible->fresh()->visibilityEvents);
    }

    /**
     * Laudo suprimido por um admin que foi excluído depois (`nullOnDelete`) continua ganhando o
     * evento: a supressão aconteceu, e perder a prova dela por causa da conta do ator seria o mesmo
     * buraco de novo. O ator fica nulo, que é o que os dados dizem.
     */
    public function test_a_suppression_whose_admin_is_gone_still_gets_its_event(): void
    {
        $inspection = $this->inspection();
        $inspection->forceFill([
            'hidden_from_public_at' => now()->subYear(),
            'hidden_by_user_id' => null,
            'hidden_reason' => 'Pedido de remoção honrado.',
        ])->saveQuietly();

        $this->rerunMigration();

        $event = InspectionVisibilityEvent::query()->sole();

        $this->assertNull($event->actor_user_id);
        $this->assertSame('Pedido de remoção honrado.', $event->reason);
    }

    /**
     * Na OS o backfill exige `hidden_by_user_id` não nulo, e a diferença é dos dados: `maintenances`
     * tem linhas do fluxo antigo, em que o próprio proprietário se opôs à publicação. Semear "a
     * equipe suprimiu" nelas inventaria um ato que não houve — e é essa mesma diferença que o
     * hodômetro usa para decidir o que conta (.ai/rules/models.md).
     */
    public function test_the_old_owner_opt_out_is_not_seeded_as_a_team_suppression(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create();
        $vehicle = Vehicle::factory()->create(['year' => now()->year - 6]);

        $byTeam = Maintenance::factory()->create(['vehicle_id' => $vehicle->id]);
        $byTeam->forceFill([
            'hidden_from_public_at' => now()->subMonth(),
            'hidden_by_user_id' => $admin->id,
            'hidden_reason' => 'Fraude confirmada.',
        ])->saveQuietly();

        $byOwner = Maintenance::factory()->create(['vehicle_id' => $vehicle->id]);
        $byOwner->forceFill([
            'hidden_from_public_at' => now()->subYear(),
            'hidden_by_user_id' => null,
        ])->saveQuietly();

        $this->rerunMigration();

        $events = MaintenanceVisibilityEvent::query()->get();

        $this->assertCount(1, $events);
        $this->assertSame($byTeam->id, $events->first()->maintenance_id);
        $this->assertSame('Fraude confirmada.', $events->first()->reason);
        $this->assertCount(0, $byOwner->fresh()->visibilityEvents);
    }

    /**
     * `vehicle_inspections.hidden_reason` é `text`, sem teto no banco: hoje a tela limita a 500
     * caracteres, mas a coluna aceita mais, e o backfill copia o que estiver lá. Com a trilha em
     * `string(500)` essa cópia estreitava sem avisar — no Postgres o insert estouraria (22001) e
     * levaria a migração junto, no Neon, com dados reais. A trilha é lida como prova: ela acomoda o
     * que a origem guardava.
     *
     * **Este teste só prova algo no Postgres** (`phpunit.pgsql.xml`): o SQLite ignora o tamanho
     * declarado em `varchar`, então ele passaria lá mesmo com a coluna estreita. É um dos casos em
     * que rodar os dois engines não é zelo, é o que separa o teste do enfeite.
     */
    public function test_a_reason_longer_than_the_form_allows_survives_the_backfill(): void
    {
        $reason = str_repeat('Laudo com chassi divergente. ', 40);

        $this->assertGreaterThan(500, mb_strlen($reason), 'O motivo precisa passar do teto da tela.');

        $inspection = $this->inspection();
        $inspection->forceFill([
            'hidden_from_public_at' => now()->subMonth(),
            'hidden_reason' => $reason,
        ])->saveQuietly();

        $this->rerunMigration();

        $this->assertSame($reason, InspectionVisibilityEvent::query()->sole()->reason);
    }

    /**
     * `down()` funcionando é requisito da entrega: a migração tem de voltar atrás no Neon.
     */
    public function test_down_drops_both_trails(): void
    {
        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('inspection_visibility_events'));
        $this->assertFalse(Schema::hasTable('maintenance_visibility_events'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('inspection_visibility_events'));
        $this->assertTrue(Schema::hasTable('maintenance_visibility_events'));
    }

    /**
     * Derruba as duas tabelas e roda a migração de novo, com as linhas suprimidas já no banco: é a
     * forma que o Neon tem no momento em que ela roda.
     */
    private function rerunMigration(): void
    {
        $this->migration()->down();
        $this->migration()->up();
    }

    private function migration(): Migration
    {
        return require base_path(self::MIGRATION);
    }

    private function inspection(): VehicleInspection
    {
        return VehicleInspection::factory()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);
    }
}
