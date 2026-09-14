<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopMessageDispatch extends Model
{
    /** @use HasFactory<\Database\Factories\WorkshopMessageDispatchFactory> */
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'user_id',
        'vehicle_id',
        'template_id',
        'dedupe_key',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkshopMessageTemplate::class, 'template_id');
    }
}
