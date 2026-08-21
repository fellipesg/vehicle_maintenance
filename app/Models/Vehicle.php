<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
    ];

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
        'cover_photo_path',
        'current_kilometers',
        'odometer_at_registration',
    ];

    protected function casts(): array
    {
        return [
            'current_kilometers' => 'integer',
            'odometer_at_registration' => 'integer',
        ];
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->cover_photo_path === null || $this->cover_photo_path === '') {
                return null;
            }

            return AppStorage::url($this->cover_photo_path);
        });
    }

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
            ->using(UserVehicle::class)
            ->withPivot(
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
