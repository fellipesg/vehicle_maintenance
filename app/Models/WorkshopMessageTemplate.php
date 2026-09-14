<?php

namespace App\Models;

use App\Enums\WorkshopMessageTrigger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkshopMessageTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\WorkshopMessageTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'tenant_id',
        'trigger',
        'service_category',
        'title',
        'body',
        'lead_kilometers',
        'min_days_since_service',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => WorkshopMessageTrigger::class,
            'lead_kilometers' => 'integer',
            'min_days_since_service' => 'integer',
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

    public function dispatches(): HasMany
    {
        return $this->hasMany(WorkshopMessageDispatch::class, 'template_id');
    }
}
