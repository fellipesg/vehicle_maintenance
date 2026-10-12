<?php

namespace App\Models;

use Database\Factories\MaintenanceInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Convite que a oficina fez ao cliente por causa de uma OS sem proprietário: no máximo um por OS.
 * Guarda só o necessário: o token da página pública, o hash do e-mail (nunca o endereço) e as datas.
 */
class MaintenanceInvite extends Model
{
    /** @use HasFactory<MaintenanceInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'maintenance_id',
        'workshop_id',
        'token',
        'email_hash',
        'email_invited_at',
        'whatsapp_invited_at',
    ];

    protected function casts(): array
    {
        return [
            'email_invited_at' => 'datetime',
            'whatsapp_invited_at' => 'datetime',
        ];
    }

    public static function hashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
