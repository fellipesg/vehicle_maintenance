<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'license_plate',
        'renavam',
        'crv_number',
        'brand',
        'model',
        'year',
        'color',
        'chassis',
        'motorization',
        'engine',
    ];

    /**
     * Get all maintenances for this vehicle
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_vehicles')
            ->withPivot(
                'purchase_date',
                'sale_date',
                'is_current_owner',
                'tenant_id',
                'ownership_verified_at',
                'crlv_exercise_year',
                'owner_document',
                'ownership_type',
            )
            ->withTimestamps();
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(VehicleAccessGrant::class);
    }

    public static function findByRenavam(string $renavam): ?self
    {
        $normalized = preg_replace('/\D/', '', $renavam);

        return static::query()
            ->where('renavam', $normalized)
            ->orWhere('renavam', $renavam)
            ->first();
    }
}
