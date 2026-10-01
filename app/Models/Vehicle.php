<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Gate;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_photo_url',
        'cover_photo_portrait_url',
        'cover_photo_thumb_url',
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
        'cover_photo_thumb_path',
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

    protected function coverPhotoThumbUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (is_string($this->cover_photo_thumb_path) && $this->cover_photo_thumb_path !== '') {
                return AppStorage::coversUrl($this->cover_photo_thumb_path);
            }

            return $this->cover_photo_portrait_url ?? $this->cover_photo_url;
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

    public function hasThumbCover(): bool
    {
        return is_string($this->cover_photo_thumb_path) && $this->cover_photo_thumb_path !== '';
    }

    /**
     * Get all maintenances for this vehicle
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Consulta do histórico limitada ao que o usuário pode ler: tudo para o dono atual, e só o que a
     * própria loja registrou para o lojista em consignação sem liberação do proprietário.
     *
     * @return HasMany<Maintenance, Vehicle>
     */
    public function maintenancesVisibleTo(User $viewer): HasMany
    {
        $relation = $this->maintenances();

        if (! Gate::forUser($viewer)->allows('viewFullHistory', $this)) {
            $relation->where('maintenances.tenant_id', $viewer->tenant_id);
        }

        return $relation;
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

    /**
     * Deixa o histórico deste veículo pré-carregado com o que o usuário pode ler, para que ninguém
     * rio abaixo precise repetir a regra: VehicleTimelineBuilder e <x-vehicle.detail> usam a relação
     * já carregada (loadMissing / relationLoaded) em vez de consultar de novo.
     *
     * Não dá para fazer isso dentro de maintenances(): o eager loading do Eloquent resolve a relação
     * num newInstance() do model, onde qualquer estado guardado na instância se perde.
     */
    public function restrictHistoryTo(User $viewer): static
    {
        $this->setRelation('maintenances', $this->maintenancesVisibleTo($viewer)->get());

        return $this;
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

    public function consignments(): HasMany
    {
        return $this->hasMany(VehicleConsignment::class);
    }

    public function activeConsignment(): HasOne
    {
        return $this->hasOne(VehicleConsignment::class)
            ->where('status', VehicleConsignment::STATUS_ACTIVE)
            ->latestOfMany();
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
