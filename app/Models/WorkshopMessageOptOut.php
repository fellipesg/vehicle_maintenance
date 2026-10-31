<?php

namespace App\Models;

use Database\Factories\WorkshopMessageOptOutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cliente que não quer mais mensagens de uma oficina (workshop_id) ou de todas (workshop_id nulo).
 * Uma linha cala todos os canais daquela oficina — e-mail, aviso no app e push: a tabela não tem
 * coluna de canal de propósito. Lido por App\Support\Workshop\WorkshopMessageConsent, que é o
 * único lugar que grava aqui (pelo link assinado do e-mail de follow-up).
 */
class WorkshopMessageOptOut extends Model
{
    /** @use HasFactory<WorkshopMessageOptOutFactory> */
    use HasFactory;

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
