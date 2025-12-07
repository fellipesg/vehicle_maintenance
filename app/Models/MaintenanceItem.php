<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

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
}
