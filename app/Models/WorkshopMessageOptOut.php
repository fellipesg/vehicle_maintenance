<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cliente que não quer mais mensagens de uma oficina (workshop_id) ou de todas (workshop_id nulo).
 * Lido por App\Support\Workshop\WorkshopMessageConsent.
 */
class WorkshopMessageOptOut extends Model
{
    protected $fillable = [
        'user_id',
        'workshop_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
