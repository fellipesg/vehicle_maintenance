<?php

namespace App\Models;

use App\Enums\WarrantyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WarrantyTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'tenant_id',
        'name',
        'body',
        'duration_days',
        'scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'scope' => WarrantyScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function maintenanceWarranties(): HasMany
    {
        return $this->hasMany(MaintenanceWarranty::class);
    }

    public function isReferenced(): bool
    {
        return $this->maintenanceWarranties()->exists();
    }
}
