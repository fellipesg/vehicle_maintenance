<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Service extends Model
{
    protected $fillable = [
        'workshop_id',
        'name',
        'description',
        'reminder_kilometers',
        'reminder_days',
    ];

    protected $casts = [
        'reminder_kilometers' => 'integer',
        'reminder_days' => 'integer',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'service_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function notificationSettings(): HasOne
    {
        return $this->hasOne(ServiceNotificationSetting::class);
    }
}
