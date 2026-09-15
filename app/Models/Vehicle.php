<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'cover_photo_portrait_url',
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
        'cover_photo_portrait_path',
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

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if ($vehicle->chassis !== null && $vehicle->chassis !== '') {
                $vehicle->chassis = self::normalizeChassis((string) $vehicle->chassis);
            }
        });
    }

    public static function normalizeChassis(string $chassis): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $chassis) ?? '');
    }

    public static function findByChassis(string $chassis): ?self
    {
        $normalized = self::normalizeChassis($chassis);

        if ($normalized === '') {
            return null;
        }

        return static::query()->where('chassis', $normalized)->first();
    }

    protected function coverPhotoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->cover_photo_path === null || $this->cover_photo_path === '') {
                return null;
            }

            return AppStorage::coversUrl($this->cover_photo_path);
        });
    }

    protected function coverPhotoPortraitUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->cover_photo_portrait_path === null || $this->cover_photo_portrait_path === '') {
                return null;
            }

            return AppStorage::coversUrl($this->cover_photo_portrait_path);
        });
    }

    public function coverPathForPdf(): ?string
    {
        $landscape = $this->cover_photo_path;
        if (is_string($landscape) && $landscape !== '') {
            return $landscape;
        }

        $portrait = $this->cover_photo_portrait_path;
        if (is_string($portrait) && $portrait !== '') {
            return $portrait;
        }

        return null;
    }

    public function hasLandscapeCover(): bool
    {
        return is_string($this->cover_photo_path) && $this->cover_photo_path !== '';
    }

    public function hasPortraitCover(): bool
    {
        return is_string($this->cover_photo_portrait_path) && $this->cover_photo_portrait_path !== '';
    }

    /**
     * Get all maintenances for this vehicle
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function plates(): HasMany
    {
        return $this->hasMany(VehiclePlate::class);
    }

    public function currentPlate(): HasOne
    {
        return $this->hasOne(VehiclePlate::class)->whereNull('ended_at')->latestOfMany();
    }

    public function provenanceStripMaintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class)
            ->select(['id', 'vehicle_id', 'maintenance_date', 'verified_at', 'maintenance_type', 'registered_by_type'])
            ->orderBy('maintenance_date')
            ->orderBy('id');
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
