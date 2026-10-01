<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePlate extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'plate',
        'started_at',
        'ended_at',
        'source',
        'changed_by_user_id',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Coluna "Origem" do histórico de placas (ficha do veículo em todos os portais e PDF), com o
     * glossário: nunca o valor interno. "manual" vale tanto para a placa digitada no cadastro quanto
     * para a trocada em "Editar veículo", e a tabela é vista por outras contas (admin, próximo dono),
     * então o texto não diz quem digitou.
     */
    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'crlv_import' => 'CRLV-e',
            'manual' => 'Informada manualmente',
            'api' => 'Cadastro pelo app',
            default => 'Cadastro',
        };
    }
}
