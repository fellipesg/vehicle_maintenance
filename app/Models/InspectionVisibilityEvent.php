<?php

namespace App\Models;

use App\Enums\VisibilityEventAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha por supressão e por devolução de um laudo: quem, por quê e quando.
 *
 * Existe porque as colunas de estado (`hidden_from_public_at`, `hidden_by_user_id`,
 * `hidden_reason`) são um interruptor, e devolver o laudo ao histórico apaga as três. Sem esta
 * trilha, republicar não deixava sinal nenhum de que a supressão existiu — ninguém descobriria que
 * o laudo já esteve fora do ar, quem o tirou nem com que justificativa. Num produto cujo valor é
 * procedência, auditar a própria supressão é o mínimo (.ai/rules/inspections.md).
 *
 * **Append-only.** O histórico é uma sequência, não um par de campos: `updated()` e `deleted()` são
 * bloqueados no boot, e a única escrita é
 * App\Services\Inspection\InspectionPublicVisibilityService. É a mesma natureza do que
 * .ai/rules/messaging.md chama de trilha auditável — tabela imutável com ator e timestamp.
 *
 * `occurred_at` é quando o ato aconteceu e `created_at` quando a linha foi gravada: as duas
 * divergem nos eventos semeados pela migração, que carregam o `hidden_from_public_at` original de um
 * laudo suprimido antes desta tabela existir.
 */
class InspectionVisibilityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_inspection_id',
        'action',
        'actor_user_id',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => VisibilityEventAction::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Trilha de auditoria não se corrige nem se apaga: um evento errado é seguido de outro, nunca
     * reescrito. Sem isto a tabela seria só um par de campos com mais linhas.
     */
    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }

    /**
     * Quem decidiu. Nulo quando a conta do admin foi removida depois (`nullOnDelete`) ou quando a
     * migração semeou um evento de um laudo cujo `hidden_by_user_id` já era nulo: o evento continua
     * valendo como prova de que a supressão aconteceu.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isHidden(): bool
    {
        return $this->action === VisibilityEventAction::Hidden;
    }

    /**
     * Ordem em que a trilha se lê: do mais antigo para o mais novo, porque é uma sequência. O `id`
     * desempata dois eventos no mesmo segundo — supressão e devolução no mesmo minuto ordenadas
     * só por data chegariam invertidas na tela.
     *
     * @param  Builder<InspectionVisibilityEvent>  $query
     * @return Builder<InspectionVisibilityEvent>
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('occurred_at')->orderBy('id');
    }
}
