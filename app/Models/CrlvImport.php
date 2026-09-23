<?php

namespace App\Models;

use App\Services\Crlv\CrlvParseResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRLV-e lido e ainda não confirmado. Fica fora da sessão: em produção ela
 * viaja dentro de um cookie, que o navegador descarta acima de 4 KB.
 */
class CrlvImport extends Model
{
    use HasFactory, HasUuids, Prunable;

    /** Tempo de vida de um cadastro em andamento, igual ao da sessão. */
    public const LIFETIME_MINUTES = 120;

    protected $fillable = [
        'user_id',
        'token',
        'mode',
        'vehicle_id',
        'parsed',
        'pending_vehicle_data',
        'source_filename',
        'consumed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parsed' => 'encrypted:array',
            'pending_vehicle_data' => 'encrypted:array',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Documento pessoal não fica guardado além do cadastro em andamento. */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at?->isFuture();
    }

    public function toParseResult(): CrlvParseResult
    {
        return CrlvParseResult::fromPreview($this->parsed);
    }

    public function markConsumed(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }
}
