<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Oficina que clientes citaram pelo nome, sem conta no RevisaLog (App\Services\Workshop\WorkshopLeadRecorder).
 * O admin convida pelo contato que o cliente informou e, quando ela se cadastra, liga as
 * manutenções citadas à oficina nova, que entram na fila de validação dela.
 */
class WorkshopLead extends Model
{
    /** @use HasFactory<\Database\Factories\WorkshopLeadFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'city',
        'state',
        'contact_email',
        'contact_phone',
        'mentions_count',
        'last_mentioned_at',
        'invited_at',
        'workshop_id',
    ];

    protected function casts(): array
    {
        return [
            'mentions_count' => 'integer',
            'last_mentioned_at' => 'datetime',
            'invited_at' => 'datetime',
        ];
    }

    public static function normalizeName(string $name): string
    {
        return (string) Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function isConverted(): bool
    {
        return $this->workshop_id !== null;
    }

    /**
     * Link do WhatsApp com a mensagem de convite, ou null sem telefone.
     */
    public function whatsappInviteUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->contact_phone);

        if ($digits === null || strlen($digits) < 10) {
            return null;
        }

        if (! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        $text = "Olá! {$this->mentions_count} cliente(s) registraram serviços da {$this->name} no RevisaLog. "
            .'Fale com a equipe para cadastrar a oficina, confirmar esses serviços e emitir o Selo da oficina: '
            .route('contact.show', ['assunto' => 'partnership']);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
