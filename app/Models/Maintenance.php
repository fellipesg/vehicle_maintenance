<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use HasFactory;
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'maintenance_type',
        'description',
        'workshop_name',
        'maintenance_date',
        'kilometers',
        'service_category',
        'is_manufacturer_required',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'is_manufacturer_required' => 'boolean',
    ];

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
}
