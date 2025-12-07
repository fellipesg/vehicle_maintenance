<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'maintenance_id',
        'maintenance_item_id',
        'invoice_type',
        'file_path',
        'file_name',
        'invoice_number',
        'invoice_date',
        'total_amount',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the maintenance that owns this invoice
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    /**
     * Get the maintenance item (if this is an item-specific invoice)
     */
    public function maintenanceItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class);
    }
}
