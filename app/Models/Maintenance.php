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
use Illuminate\Support\Facades\Gate;

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
            'owner_disputed_at' => 'datetime',
            'workshop_review_status' => WorkshopReviewStatus::class,
            'workshop_reviewed_at' => 'datetime',
            'workshop_review_requested_at' => 'datetime',
            'workshop_review_reminded_at' => 'datetime',
        ];
    }

    /**
     * Resultado de isVerifiedCurrentOwner por usuário, para as telas e resources que perguntam
     * várias vezes sobre a mesma OS.
     *
     * @var array<int, bool>
     */
    private array $verifiedCurrentOwnerCache = [];

    protected static function booted(): void
    {
        // Os anexos saem do storage junto com a OS: a exclusão em cascata do banco não dispara os
        // eventos do model, então as fotos (e as variantes) são apagadas uma a uma aqui.
        static::deleting(function (Maintenance $maintenance): void {
            $maintenance->photos()->get()->each->delete();
        });
    }

    /**
     * A OS de oficina nasceu num carro sem proprietário atual e depende da decisão de quem assumir
     * o veículo (owner_status não nulo). Num carro que já tem dono, owner_status é nulo: o registro
     * entra no histórico dele direto, com o selo, e o que ele tem é contestar.
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
     * O proprietário atual contestou este registro: ele diz que o serviço não aconteceu no carro
     * dele (MaintenanceOwnerDecisionService::dispute).
     *
     * Contestar **marca, não apaga**: o registro continua aparecendo em todas as superfícies, com o
     * aviso de que o proprietário contesta. O histórico pertence ao chassi e é o ativo do produto;
     * tirá-lo de circulação por declaração de uma das partes é o que a contestação substituiu
     * (antes o dono com CRLV-e ocultava, e o comprador não distinguia "carro sem histórico" de
     * "histórico suprimido"). Esconder de verdade ficou só com a equipe, depois de analisar
     * (App\Services\Maintenance\MaintenancePublicVisibilityService).
     */
    public function isDisputed(): bool
    {
        return $this->owner_disputed_at !== null;
    }

    /**
     * Pode ser contestado? Qualquer OS que uma oficina registrou (registered_by_type workshop),
     * com selo ou sem. Contestar é a única resposta que o proprietário tem — e, como só marca, vale
     * para a selada também: é o caso do dono que chega depois e encontra no chassi dele um serviço
     * que não reconhece. O que ele declarou (owner) ou o lojista declarou (garage) ele resolve
     * editando ou excluindo, não contestando.
     */
    public function isDisputable(): bool
    {
        return $this->registered_by_type === 'workshop';
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
     * Exceção (prévia do proprietário): o dono atual do veículo com a posse comprovada
     * (ownership_verified_at, via CRLV-e) vê as notas e fotos antes de aceitar, porque é ele quem
     * decide e precisa decidir informado. Comprador, outro tenant, dono sem comprovação e visitante
     * continuam sem ver nada.
     */
    public function hidesAttachmentsFrom(?User $viewer): bool
    {
        return $this->isOwnerlessRecord()
            && $this->attachments_status !== self::ATTACHMENTS_ACCEPTED
            && ! $this->isCreatedByWorkshopOf($viewer)
            && ! $this->isVerifiedCurrentOwner($viewer);
    }

    /**
     * Dono atual do veículo, no tenant dele, com a posse comprovada pelo CRLV-e: é a mesma noção
     * de "comprovou a posse" que o MaintenanceOwnerDecisionService::ownershipVerified usa para
     * deixar decidir sobre o registro.
     */
    public function isVerifiedCurrentOwner(?User $viewer): bool
    {
        if ($viewer === null || $viewer->tenant_id === null || $this->vehicle_id === null) {
            return false;
        }

        return $this->verifiedCurrentOwnerCache[$viewer->id] ??= UserVehicle::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->where('user_id', $viewer->id)
            ->where('tenant_id', $viewer->tenant_id)
            ->where('is_current_owner', true)
            ->whereNotNull('ownership_verified_at')
            ->exists();
    }

    /**
     * Quem vê todas as fotos da OS (antes, durante, peça): a oficina autora, o tenant dono do
     * registro e, numa OS sem proprietário, o dono atual com posse comprovada. Os demais veem só
     * veículo + depois (as públicas).
     */
    public function exposesAllPhotosTo(?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($viewer->isWorkshop() && $viewer->workshop) {
            return $this->workshop_id === $viewer->workshop->id;
        }

        if ($this->tenant_id !== null && $this->tenant_id === $viewer->tenant_id) {
            return true;
        }

        return $this->isOwnerlessRecord() && $this->isVerifiedCurrentOwner($viewer);
    }

    /**
     * Fora da consulta pública e do histórico: oculta pela equipe
     * (MaintenancePublicVisibilityService) ou, em dados anteriores à mudança, pelo proprietário. Só
     * a oficina autora e quem decidiu ainda a veem; o admin enxerga por outro caminho (as telas de
     * admin não passam por aqui).
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
     * Este registro aparece no histórico que este leitor vê deste veículo?
     *
     * É a forma em memória, para um registro só, da consulta que Vehicle::maintenancesVisibleTo
     * monta: o scope acima mais a restrição de tenant de quem não passa em viewFullHistory. As duas
     * precisam responder a mesma coisa — autorizar a manutenção, a foto ou a nota pela rota de
     * detalhe tem de deixar entrar exatamente quem veria aquele registro na listagem do veículo,
     * senão o lojista em consignação enumera ids e abre o que a listagem esconde dele (anexo de dono
     * anterior). Use isto em toda autorização de registro individual, nunca VehiclePolicy::view: ver
     * o veículo não é ver o histórico que ele trazia.
     */
    public function isVisibleInHistoryTo(User $viewer): bool
    {
        if ($this->hiddenFrom($viewer)) {
            return false;
        }

        if ($viewer->tenant_id !== null && $this->tenant_id === $viewer->tenant_id) {
            return true;
        }

        // loadMissing, não $this->vehicle: a policy roda com o que o controller carregou, e o
        // preventLazyLoading fora de produção quebraria a autorização em vez de respondê-la.
        $this->loadMissing('vehicle');

        $vehicle = $this->getRelationValue('vehicle');

        return $vehicle instanceof Vehicle
            && Gate::forUser($viewer)->allows('viewFullHistory', $vehicle);
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
     * A OS é desta oficina, isto é, foi ela que a registrou?
     *
     * Com o Selo da oficina é o verified_workshop_id. Sem selo é o workshop_id junto com
     * registered_by_type workshop — não existe mais caminho que crie OS de oficina sem selo, mas
     * dados de um período em que o selo dependia de vínculo provado continuam valendo, e a oficina
     * precisa poder corrigir e excluir o que registrou. A declarada que só CITA a oficina
     * (workshop_id com registered_by_type owner ou garage) segue de quem declarou.
     */
    public function isAuthoredByWorkshopId(?int $workshopId): bool
    {
        if ($workshopId === null) {
            return false;
        }

        if ($this->isVerified()) {
            return (int) $this->verified_workshop_id === $workshopId;
        }

        return $this->registered_by_type === 'workshop'
            && (int) $this->workshop_id === $workshopId;
    }

    /**
     * Registros que contam para o hodômetro do veículo (VehicleMileageService).
     *
     * Fica de fora o que o proprietário contestou: ele diz que aquele serviço não aconteceu no
     * carro dele, e até a equipe analisar o número não pode governar o `current_kilometers` — era
     * por aí que uma quilometragem absurda entrava e não saía mais. O registro continua no
     * histórico, com a marca; só não manda no hodômetro.
     *
     * @param  Builder<Maintenance>  $query
     * @return Builder<Maintenance>
     */
    public function scopeCountsTowardOdometer(Builder $query): Builder
    {
        return $query->whereNull('owner_disputed_at');
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
            'workshop' => $this->isVerified() ? 'Selo da oficina' : 'Registrada pela oficina',
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

            return $this->provenance_label.' · '.$name;
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
     * Quem da equipe tirou o registro da consulta pública.
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }

    /**
     * Quem contestou o registro (o proprietário atual na época).
     */
    public function disputedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_disputed_by_user_id');
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
