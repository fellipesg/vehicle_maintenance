<?php

use App\Enums\VisibilityEventAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A trilha de supressão de laudos e de OS: uma linha por supressão e por devolução, com ator,
 * motivo e horário.
 *
 * As colunas de estado (`hidden_from_public_at`, `hidden_by_user_id`, `hidden_reason`) são um
 * interruptor: devolver o registro ao histórico zera as três, e até aqui não sobrava registro de que
 * a supressão existiu. Quem precisasse auditar não descobriria que o laudo já esteve fora do ar, quem
 * o tirou nem com que justificativa — num produto cujo valor é procedência, é o buraco que só
 * aparece quando alguém precisa da prova.
 *
 * Duas tabelas, não uma com relação polimórfica: cada trilha pende da sua entidade com chave
 * estrangeira de verdade, então apagar o laudo ou a OS leva a trilha dela e nenhuma linha fica
 * órfã. É a mesma razão de `vehicle_inspections` ser tabela própria (.ai/rules/inspections.md).
 *
 * As colunas de estado **continuam** sendo o estado corrente, e a devolução continua zerando as
 * três: `Maintenance::scopeCountsTowardOdometer` distingue a supressão da equipe
 * (`hidden_by_user_id` não nulo) do ocultar antigo do proprietário, e manter o nome preso ali depois
 * de republicar tiraria a OS da conta do hodômetro para sempre (.ai/rules/models.md).
 */
return new class extends Migration
{
    /**
     * Linhas por insert do backfill. O volume é pequeno (supressão é rara e manual), mas a migração
     * roda no Neon com dados reais e um insert único de tamanho indeterminado é o tipo de coisa que
     * estoura o limite de parâmetros do Postgres sem aviso.
     */
    private const BACKFILL_CHUNK = 200;

    public function up(): void
    {
        Schema::create('inspection_visibility_events', function (Blueprint $table) {
            $this->defineEventTable($table, 'vehicle_inspection_id', 'vehicle_inspections');
        });

        Schema::create('maintenance_visibility_events', function (Blueprint $table) {
            $this->defineEventTable($table, 'maintenance_id', 'maintenances');
        });

        $this->backfillInspections();
        $this->backfillMaintenances();
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_visibility_events');
        Schema::dropIfExists('inspection_visibility_events');
    }

    /**
     * Forma compartilhada das duas trilhas. Escrever as duas iguais aqui é o que impede uma de
     * ganhar coluna que a outra não tem — os serviços são irmãos e a equipe lê um esperando o outro.
     */
    private function defineEventTable(Blueprint $table, string $foreignKey, string $foreignTable): void
    {
        $table->id();
        $table->foreignId($foreignKey)->constrained($foreignTable)->cascadeOnDelete();
        $table->string('action', 20)
            ->comment('hidden | restored (App\Enums\VisibilityEventAction)');
        $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete()
            ->comment('Quem decidiu; nulo se a conta foi removida depois');
        $table->string('reason', 500)->nullable()
            ->comment('Motivo informado na supressão; a devolução hoje não pede motivo');
        $table->timestamp('occurred_at')
            ->comment('Quando o ato aconteceu — nos eventos semeados, o hidden_from_public_at original');
        $table->timestamps();

        $table->index([$foreignKey, 'occurred_at']);
    }

    /**
     * Laudo suprimido agora ganha o evento da supressão que o deixou assim, para o histórico não
     * começar vazio onde já existe estado. `hidden_*` do laudo só é escrito pela equipe
     * (InspectionPublicVisibilityService), então toda linha suprimida é uma supressão de equipe —
     * mesmo as de `hidden_by_user_id` nulo, que são as de admin excluído depois.
     */
    private function backfillInspections(): void
    {
        $this->seedEvents(
            DB::table('vehicle_inspections')->whereNotNull('hidden_from_public_at'),
            'inspection_visibility_events',
            'vehicle_inspection_id',
        );
    }

    /**
     * Na OS o backfill exige `hidden_by_user_id` **não nulo**, e essa diferença em relação ao laudo
     * é dos dados, não de descuido: `maintenances` tem linhas do fluxo antigo, em que o próprio
     * proprietário se opunha à publicação, e elas têm `hidden_from_public_at` com
     * `hidden_by_user_id` nulo. Semear "a equipe suprimiu" nelas seria inventar um ato que não
     * houve, e é justamente essa diferença que o hodômetro usa (.ai/rules/models.md).
     *
     * Nessas linhas a trilha nasce vazia, e é o correto: a tabela não sabe nada de antes de existir.
     */
    private function backfillMaintenances(): void
    {
        $this->seedEvents(
            DB::table('maintenances')
                ->whereNotNull('hidden_from_public_at')
                ->whereNotNull('hidden_by_user_id'),
            'maintenance_visibility_events',
            'maintenance_id',
        );
    }

    /**
     * @param  Builder  $source  registros suprimidos da tabela de origem
     */
    private function seedEvents(Builder $source, string $eventTable, string $foreignKey): void
    {
        $now = now();

        $source
            ->select(['id', 'hidden_from_public_at', 'hidden_by_user_id', 'hidden_reason'])
            ->orderBy('id')
            ->chunk(self::BACKFILL_CHUNK, function ($records) use ($eventTable, $foreignKey, $now): void {
                $rows = [];

                foreach ($records as $record) {
                    $rows[] = [
                        $foreignKey => $record->id,
                        'action' => VisibilityEventAction::Hidden->value,
                        'actor_user_id' => $record->hidden_by_user_id,
                        'reason' => $record->hidden_reason,
                        'occurred_at' => $record->hidden_from_public_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table($eventTable)->insert($rows);
                }
            });
    }
};
