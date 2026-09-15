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

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'manual' => 'Manual',
            'crlv_import' => 'Importação CRLV',
            'backfill' => 'Migração',
            'api' => 'API',
            default => $source,
        };
    }
}
