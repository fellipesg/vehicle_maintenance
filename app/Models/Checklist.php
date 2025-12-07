<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checklist extends Model
{
    protected $fillable = [
        'maintenance_id',
        'checklist_type',
        'items',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    /**
     * Get the maintenance that owns this checklist
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }
}
