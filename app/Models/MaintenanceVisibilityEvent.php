<?php

namespace App\Models;

use App\Enums\VisibilityEventAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha por supressão e por devolução de uma OS: quem, por quê e quando. Irmã de
 * App\Models\InspectionVisibilityEvent, e pelo mesmo motivo — ver o docblock dela.
 *
 * Tabela separada da trilha do laudo, e não uma só com relação polimórfica, porque cada trilha
 * pende da sua entidade com chave estrangeira de verdade: apagar a OS leva a trilha dela
 * (`cascadeOnDelete`), e um `maintenance_id` polimórfico não teria essa garantia no banco. É a mesma
 * razão por que `vehicle_inspections` não é uma categoria de `maintenances`
 * (.ai/rules/inspections.md).
 *
 * **Append-only**, como a irmã: a única escrita é
 * App\Services\Maintenance\MaintenancePublicVisibilityService.
 */
class MaintenanceVisibilityEvent extends Model
{
    use HasFactory;

    /**
     * Teto do motivo, o mesmo de `maintenances.hidden_reason` e do campo da tela do admin.
     */
    public const MAX_REASON_LENGTH = 500;

    protected $fillable = [
        'maintenance_id',
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

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isHidden(): bool
    {
        return $this->action === VisibilityEventAction::Hidden;
    }

    /**
     * @param  Builder<MaintenanceVisibilityEvent>  $query
     * @return Builder<MaintenanceVisibilityEvent>
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('occurred_at')->orderBy('id');
    }
}
