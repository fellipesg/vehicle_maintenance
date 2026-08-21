<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserVehicle extends Pivot
{
    protected $table = 'user_vehicles';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'purchase_date',
        'sale_date',
        'is_current_owner',
        'tenant_id',
        'ownership_verified_at',
        'crlv_exercise_year',
        'owner_document',
        'ownership_type',
        'terms_accepted_at',
        'terms_version',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'sale_date' => 'date',
            'is_current_owner' => 'boolean',
            'ownership_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'owner_document' => 'encrypted',
            'crlv_exercise_year' => 'integer',
        ];
    }
}
