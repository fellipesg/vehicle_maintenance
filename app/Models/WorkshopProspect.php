<?php

namespace App\Models;

use App\Enums\WorkshopProspectStatus;
use Database\Factories\WorkshopProspectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Oficina encontrada no cadastro público de CNPJ (Receita Federal) que pode receber, no máximo,
 * um convite e um follow-up. Ver .ai/rules/outreach.md.
 */
class WorkshopProspect extends Model
{
    /** @use HasFactory<WorkshopProspectFactory> */
    use HasFactory;

    protected $fillable = [
        'cnpj',
        'trade_name',
        'legal_name',
        'email',
        'phone',
        'cnae',
        'street',
        'number',
        'neighborhood',
        'cep',
        'city',
        'state',
        'source',
        'token',
        'status',
        'first_sent_at',
        'first_message_id',
        'follow_up_sent_at',
        'clicked_at',
        'replied_at',
        'converted_at',
        'unsubscribed_at',
        'last_error',
        'workshop_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkshopProspectStatus::class,
            'first_sent_at' => 'datetime',
            'follow_up_sent_at' => 'datetime',
            'clicked_at' => 'datetime',
            'replied_at' => 'datetime',
            'converted_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $prospect): void {
            $prospect->token ??= Str::random(40);
            $prospect->email = mb_strtolower(trim($prospect->email));
        });
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public static function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            return $cnpj;
        }

        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $cnpj;
    }

    public function formattedCnpj(): string
    {
        return self::formatCnpj($this->cnpj);
    }

    public function displayName(): string
    {
        if (filled($this->trade_name)) {
            return trim($this->trade_name);
        }

        if (filled($this->legal_name)) {
            return Str::title(mb_strtolower(trim($this->legal_name)));
        }

        return 'sua oficina';
    }

    /**
     * Já recebeu o convite e ainda pode receber o follow-up, ou ainda não recebeu nada.
     */
    public function isContactable(): bool
    {
        if (! in_array($this->status, [WorkshopProspectStatus::Pending, WorkshopProspectStatus::Sent], true)) {
            return false;
        }

        return ! EmailSuppression::isSuppressed($this->email);
    }

    /**
     * Momento a partir do qual o follow-up pode sair: N dias úteis depois do primeiro envio.
     */
    public function followUpDueAt(): ?Carbon
    {
        if ($this->first_sent_at === null) {
            return null;
        }

        return $this->first_sent_at
            ->copy()
            ->setTimezone((string) config('outreach.window.timezone'))
            ->addWeekdays((int) config('outreach.follow_up_after_business_days'))
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Teto de duas mensagens: o follow-up só sai se o status ainda é Sent (sem clique, resposta,
     * conversão, descadastro ou devolução) e se ele nunca foi enviado.
     */
    public function isFollowUpDue(): bool
    {
        return $this->status === WorkshopProspectStatus::Sent
            && $this->follow_up_sent_at === null
            && $this->followUpDueAt() !== null
            && $this->followUpDueAt()->lte(now());
    }

    /**
     * A oficina respondeu (formulário de contato ou marcação manual). Não rebaixa quem já converteu
     * nem reabre quem se descadastrou.
     */
    public function markReplied(): void
    {
        $this->replied_at ??= now();

        if (! in_array($this->status, [WorkshopProspectStatus::Converted, WorkshopProspectStatus::Unsubscribed], true)) {
            $this->status = WorkshopProspectStatus::Replied;
        }

        $this->save();
    }

    public function markConverted(?Workshop $workshop = null): void
    {
        $this->update([
            'status' => WorkshopProspectStatus::Converted,
            'converted_at' => $this->converted_at ?? now(),
            'workshop_id' => $workshop?->id ?? $this->workshop_id,
        ]);
    }
}
