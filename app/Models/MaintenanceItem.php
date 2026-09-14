<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'part_number',
        'has_warranty',
        'warranty_starts_at',
        'warranty_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'has_warranty' => 'boolean',
            'warranty_starts_at' => 'date',
            'warranty_ends_at' => 'date',
        ];
    }

    public function warrantyPeriodLabel(): ?string
    {
        $warranty = $this->warranty;

        if ($warranty === null) {
            return null;
        }

        return 'Garantia até '.$warranty->ends_at->format('d/m/Y');
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty?->isVigente() ?? false;
    }

    /**
     * Get the maintenance that owns this item
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    /**
     * Get all invoices for this item
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(MaintenanceWarranty::class);
    }
}
