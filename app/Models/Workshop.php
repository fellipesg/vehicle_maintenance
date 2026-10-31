<?php

namespace App\Models;

use App\Enums\WorkshopPlan;
use App\Enums\WorkshopStatus;
use App\Observers\WorkshopObserver;
use App\Support\AppStorage;
use App\Support\DocumentMask;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[ObservedBy(WorkshopObserver::class)]
class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'cnpj',
        'phone',
        'whatsapp',
        'email',
        'facebook',
        'instagram',
        'cep',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'latitude',
        'longitude',
        'logo_path',
        'weekly_digest_enabled',
    ];

    protected function casts(): array
    {
        return [
            'plan' => WorkshopPlan::class,
            'status' => WorkshopStatus::class,
            'weekly_digest_enabled' => 'boolean',
        ];
    }

    /**
     * Get the user (owner) of this workshop
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quem decidiu a análise do cadastro (admin).
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Oficina liberada para trabalhar: registrar OS com o Selo da oficina, convidar cliente e
     * validar declaradas. Pendente e recusada só abrem "Minha oficina"
     * (App\Http\Middleware\EnsureWorkshopApproved).
     */
    public function isApproved(): bool
    {
        return $this->status === WorkshopStatus::Approved;
    }

    /**
     * CNPJ só com dígitos, para comparar com o registro público.
     */
    public function normalizedDocument(): ?string
    {
        if ($this->cnpj === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->cnpj) ?: null;
    }

    /**
     * Documento como o admin precisa ver para conferir a oficina: CNPJ por extenso, porque é
     * número público de registro, e CPF mascarado (App\Support\DocumentMask), porque é dado
     * pessoal. Mesma regra de Garage::displayDocument.
     */
    public function displayDocument(): ?string
    {
        $digits = $this->normalizedDocument();

        if ($digits === null) {
            return null;
        }

        return strlen($digits) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits)
            : DocumentMask::cpfOrCnpj($digits);
    }

    /**
     * Endereço de uma linha ("Rua X, 10 — Centro — Londrina/PR"), vazio quando não há endereço.
     */
    public function addressLine(): string
    {
        return collect([
            collect([$this->street, $this->number])->filter(fn (mixed $part): bool => filled($part))->implode(', '),
            $this->neighborhood,
            collect([$this->city, $this->state])->filter(fn (mixed $part): bool => filled($part))->implode('/'),
        ])->filter(fn (mixed $part): bool => filled($part))->implode(' — ');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all maintenances for this workshop
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Get all items for this workshop
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get all services for this workshop
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(WorkshopMessageTemplate::class);
    }

    public function messageDispatches(): HasMany
    {
        return $this->hasMany(WorkshopMessageDispatch::class);
    }

    /**
     * Manutenções declaradas por clientes que citam a oficina e esperam a validação dela.
     */
    public function maintenancesAwaitingReview(): HasMany
    {
        return $this->maintenances()->awaitingReviewBy($this->id);
    }

    public function planOrDefault(): WorkshopPlan
    {
        return $this->plan ?? WorkshopPlan::Free;
    }

    public function warrantyTemplates(): HasMany
    {
        return $this->hasMany(WarrantyTemplate::class);
    }

    public function issuedWarranties(): HasManyThrough
    {
        return $this->hasManyThrough(
            MaintenanceWarranty::class,
            Maintenance::class,
            'workshop_id',
            'maintenance_id',
        );
    }

    public function hasActiveWarranties(): bool
    {
        $today = now()->startOfDay();

        return $this->issuedWarranties()
            ->whereDate('maintenance_warranties.ends_at', '>=', $today)
            ->exists();
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return AppStorage::urlForPath($this->logo_path);
    }

    /**
     * Get full address as a single string
     */
    public function getFullAddressAttribute(): string
    {
        $address = "{$this->street}, {$this->number}";
        if ($this->complement) {
            $address .= " - {$this->complement}";
        }
        $address .= " - {$this->neighborhood}, {$this->city}/{$this->state}";
        $address .= " - CEP: {$this->cep}";

        return $address;
    }

    /**
     * Get formatted CEP
     */
    public function getFormattedCepAttribute(): string
    {
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $this->cep);
    }
}
