<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'license_plate',
        'renavam',
        'brand',
        'model',
        'year',
        'color',
        'chassis',
        'engine',
    ];

    /**
     * Get all maintenances for this vehicle
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
