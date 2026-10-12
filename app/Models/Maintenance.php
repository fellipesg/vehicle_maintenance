<?php

namespace App\Models;

use App\Enums\WarrantyScope;
use App\Enums\WorkshopReviewStatus;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Maintenance extends Model
{
    use HasFactory;

    public const OWNER_PENDING = 'pending';

    public const OWNER_LINKED = 'linked';

    public const OWNER_DECLINED = 'declined';

    public const ATTACHMENTS_NONE = 'none';

    public const ATTACHMENTS_PENDING = 'pending';

    public const ATTACHMENTS_ACCEPTED = 'accepted';

    public const ATTACHMENTS_DECLINED = 'declined';

    public const ATTACHMENTS_REVOKED = 'revoked';

    protected $fillable = [
        'vehicle_id',
        'user_id',
        'tenant_id',
        'workshop_id',
        'maintenance_type',
        'description',
        'workshop_name',
        'maintenance_date',
        'kilometers',
        'service_category',
        'is_manufacturer_required',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'is_manufacturer_required' => 'boolean',
            'verified_at' => 'datetime',
            'hidden_from_public_at' => 'datetime',
            'owner_decided_at' => 'datetime',
            'workshop_review_status' => WorkshopReviewStatus::class,
            'workshop_reviewed_at' => 'datetime',
            'workshop_review_requested_at' => 'datetime',
            'workshop_review_reminded_at' => 'datetime',
        ];
    }

    /**
     * A OS nasceu num carro sem proprietário e depende da decisão dele (owner_status não nulo).
     */
    public function isOwnerlessRecord(): bool
    {
        return $this->owner_status !== null;
    }

    public function isHiddenFromPublic(): bool
    {
        return $this->hidden_from_public_at !== null;
    }

    /**
     * Quem a registrou: a conta da oficina que criou a OS.
     */
    public function isCreatedByWorkshopOf(?User $user): bool
    {
        return $user !== null
            && $user->isWorkshop()
            && $user->workshop !== null
            && $this->workshop_id !== null
            && (int) $this->workshop_id === (int) $user->workshop->id;
    }

    /**
     * Descrição livre, valores e anexos ficam só com a oficina enquanto o proprietário não vinculou
     * (ou depois que recusou). Só a oficina que fez a OS vê o registro completo.
     */
    public function hidesDetailsFrom(?User $viewer): bool
    {
        return in_array($this->owner_status, [self::OWNER_PENDING, self::OWNER_DECLINED], true)
            && ! $this->isCreatedByWorkshopOf($viewer);
    }

    /**
     * Notas e fotos de uma OS sem dono só aparecem para outros depois do aceite do proprietário.
     */
    public function hidesAttachmentsFrom(?User $viewer): bool
    {
        return $this->isOwnerlessRecord()
            && $this->attachments_status !== self::ATTACHMENTS_ACCEPTED
            && ! $this->isCreatedByWorkshopOf($viewer);
    }

    /**
     * Fora da consulta pública e do histórico: oculta pelo proprietário (direito de oposição). Só
     * a oficina autora e quem ocultou ainda a veem.
     */
    public function hiddenFrom(?User $viewer): bool
    {
        return $this->isHiddenFromPublic()
            && ! $this->isCreatedByWorkshopOf($viewer)
            && ! ($viewer !== null && (int) $this->owner_decided_by_user_id === (int) $viewer->id);
    }

    /**
     * Registros que o histórico e a consulta pública mostram a este leitor (null = visitante).
     */
    public function scopeVisibleInHistoryTo(Builder $query, ?User $viewer): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $query) use ($table, $viewer): void {
            $query->whereNull($table.'.hidden_from_public_at');

            if ($viewer === null) {
                return;
            }

            $query->orWhere($table.'.owner_decided_by_user_id', $viewer->id);

            if ($viewer->isWorkshop() && $viewer->workshop !== null) {
                $query->orWhere($table.'.workshop_id', $viewer->workshop->id);
            }
        });
    }

    /**
     * Anexos ainda sem consentimento: enviar nota ou foto numa OS sem dono volta o status a pending.
     */
    public function markAttachmentsPendingIfNeeded(): void
    {
        if (! $this->isOwnerlessRecord() || $this->attachments_status === self::ATTACHMENTS_ACCEPTED) {
            return;
        }

        if ($this->attachments_status !== self::ATTACHMENTS_PENDING) {
            $this->forceFill(['attachments_status' => self::ATTACHMENTS_PENDING])->saveQuietly();
        }
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Declarada citando uma oficina da rede, ainda sem resposta dela.
     */
    public function isAwaitingWorkshopReview(): bool
    {
        return $this->workshop_review_status === WorkshopReviewStatus::Pending
            && $this->workshop_id !== null
            && $this->verified_at === null;
    }

    /**
     * O selo veio da confirmação de uma manutenção que o cliente declarou.
     */
    public function wasConfirmedByWorkshop(): bool
    {
        return $this->isVerified() && $this->verification_method === 'confirmed';
    }

    /**
     * A OS mudou depois da emissão do Selo da oficina (mais de um minuto depois, para não contar a
     * gravação da própria emissão). As telas mostram "Atualizada em dd/mm/aaaa" (WRK-X04).
     */
    public function wasUpdatedAfterSeal(): bool
    {
        return $this->verified_at !== null
            && $this->updated_at !== null
            && $this->updated_at->greaterThan($this->verified_at->copy()->addMinute());
    }

    public function getProvenanceLabelAttribute(): string
    {
        return match ($this->registered_by_type) {
            'workshop' => 'Selo da oficina',
            'garage' => 'Declarada pelo lojista',
            default => 'Declarada pelo proprietário',
        };
    }

    public function getProvenanceSublabelAttribute(): string
    {
        return $this->isVerified() ? 'verificada' : 'não verificada';
    }

    public function getProvenanceCardLabelAttribute(): string
    {
        if ($this->registered_by_type === 'workshop') {
            $name = $this->verifiedWorkshop?->name
                ?? $this->workshop?->name
                ?? $this->workshop_name
                ?? 'Oficina';

            return 'Selo da oficina · '.$name;
        }

        return $this->provenance_label;
    }

    public function getProvenanceMetaAttribute(): string
    {
        if ($this->isVerified()) {
            $date = DisplayTime::local($this->verified_at)?->format('d/m/Y') ?? '';
            $code = $this->verification_code ?? '';

            return trim("verificada em {$date} · {$code}");
        }

        $meta = 'não verificada';
        $invoiceCount = $this->relationLoaded('invoices')
            ? $this->invoices->count()
            : $this->invoices()->count();

        if ($invoiceCount > 0) {
            $meta .= ' · NF anexada ('.$invoiceCount.')';
        }

        return $meta;
    }

    public function verifiedWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'verified_workshop_id');
    }

    public function verificationUrl(): ?string
    {
        if ($this->verification_code === null) {
            return null;
        }

        return url('/v/'.$this->verification_code);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Declaradas que citam a oficina e esperam a validação dela.
     */
    public function scopeAwaitingReviewBy($query, int $workshopId)
    {
        return $query->where('workshop_id', $workshopId)
            ->whereNull('verified_at')
            ->where('workshop_review_status', WorkshopReviewStatus::Pending->value);
    }

    public function workshopReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workshop_reviewed_by');
    }

    public function rejectedWorkshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'rejected_workshop_id');
    }

    public function workshopLead(): BelongsTo
    {
        return $this->belongsTo(WorkshopLead::class);
    }

    /**
     * Get the vehicle that owns this maintenance
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the user who registered this maintenance
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all items for this maintenance
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    /**
     * Get all invoices for this maintenance
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all checklists for this maintenance
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    /**
     * Get the workshop for this maintenance
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenancePhoto::class)->orderBy('sort');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(MaintenanceWarranty::class);
    }

    public function generalWarranty(): HasOne
    {
        return $this->hasOne(MaintenanceWarranty::class)
            ->where('scope', WarrantyScope::Order);
    }

    public function invite(): HasOne
    {
        return $this->hasOne(MaintenanceInvite::class);
    }

    public function publicPhotos(): HasMany
    {
        return $this->photos()
            ->where('subject', MaintenancePhoto::SUBJECT_VEHICLE)
            ->where('stage', MaintenancePhoto::STAGE_AFTER);
    }

    /**
     * Official workshop name, or the free-text name when none was selected.
     */
    public function displayWorkshopName(): ?string
    {
        return $this->workshop?->name ?: $this->workshop_name;
    }
}
