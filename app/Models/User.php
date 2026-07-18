<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'is_admin',
        'tenant_id',
        'phone',
        'document',
        'subscription_active',
        'postal_code',
        'street',
        'number',
        'complement',
        'city',
        'state',
        'country',
        'provider',
        'provider_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'subscription_active' => 'boolean',
        ];
    }

    /**
     * Get all vehicles owned by this user
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'user_vehicles')
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

    /**
     * Get current vehicles (where user is current owner) scoped to tenant
     */
    public function currentVehicles(): BelongsToMany
    {
        $query = $this->vehicles()->wherePivot('is_current_owner', true);

        if ($this->tenant_id) {
            $query->wherePivot('tenant_id', $this->tenant_id);
        }

        return $query;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function garage()
    {
        return $this->hasOne(Garage::class);
    }

    /**
     * Get FCM tokens for push notifications
     */
    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    /**
     * Check if user is a common user
     */
    public function isUser(): bool
    {
        return $this->user_type === 'user';
    }

    /**
     * Check if user is a garage (dealership)
     */
    public function isGarage(): bool
    {
        return $this->user_type === 'garage';
    }

    /**
     * Check if user is a workshop
     */
    public function isWorkshop(): bool
    {
        return $this->user_type === 'workshop';
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function canViewVehicleMaintenances(?Vehicle $vehicle = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isGarage()) {
            return true;
        }

        return (bool) $this->subscription_active;
    }

    public function normalizedDocument(): ?string
    {
        if ($this->document === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->document) ?: null;
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function typeLabel(): string
    {
        return match ($this->user_type) {
            'garage' => 'Lojista',
            'workshop' => 'Oficina',
            default => 'Usuário',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->user_type) {
            'garage' => 'badge-green',
            'workshop' => 'badge-orange',
            default => 'badge-blue',
        };
    }

    /**
     * Get workshop associated with this user (if user_type is workshop)
     */
    public function workshop()
    {
        return $this->hasOne(Workshop::class, 'user_id');
    }
}
