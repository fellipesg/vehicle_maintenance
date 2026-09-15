<?php

namespace App\Models;

use App\Enums\WarrantyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Maintenance extends Model
{
    use HasFactory;

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
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
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
