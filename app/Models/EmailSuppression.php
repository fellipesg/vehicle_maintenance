<?php

namespace App\Models;

use Database\Factories\EmailSuppressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Endereço que nunca mais recebe e-mail de prospecção. Permanente: não há rotina que apague linhas.
 */
class EmailSuppression extends Model
{
    /** @use HasFactory<EmailSuppressionFactory> */
    use HasFactory;

    public const REASON_UNSUBSCRIBED = 'unsubscribed';

    public const REASON_BOUNCED = 'bounced';

    public const REASON_COMPLAINT = 'complaint';

    public const REASON_MANUAL = 'manual';

    protected $fillable = [
        'email',
        'reason',
    ];

    public static function isSuppressed(string $email): bool
    {
        return static::query()->where('email', mb_strtolower(trim($email)))->exists();
    }

    /**
     * Idempotente: se o endereço já está na lista, mantém o motivo original.
     */
    public static function suppress(string $email, string $reason): self
    {
        return static::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
            ['reason' => $reason],
        );
    }
}
