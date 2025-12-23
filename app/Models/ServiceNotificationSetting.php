<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceNotificationSetting extends Model
{
    protected $fillable = [
        'service_id',
        'notify_6_months_before',
        'notify_3_months_before',
        'notify_1_month_before',
        'notify_15_days_before',
        'notify_1_day_before',
        'notify_expired',
    ];

    protected $casts = [
        'notify_6_months_before' => 'boolean',
        'notify_3_months_before' => 'boolean',
        'notify_1_month_before' => 'boolean',
        'notify_15_days_before' => 'boolean',
        'notify_1_day_before' => 'boolean',
        'notify_expired' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
