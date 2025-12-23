<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'phone',
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
        ];
    }

    /**
     * Get all vehicles owned by this user
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'user_vehicles')
            ->withPivot('purchase_date', 'sale_date', 'is_current_owner')
            ->withTimestamps();
    }

    /**
     * Get current vehicles (where user is current owner)
     */
    public function currentVehicles(): BelongsToMany
    {
        return $this->vehicles()->wherePivot('is_current_owner', true);
    }

    /**
     * Get FCM tokens for push notifications
     */
    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class);
    }

    /**
     * Check if user is a workshop
     */
    public function isWorkshop(): bool
    {
        return $this->user_type === 'workshop';
    }

    /**
     * Get workshop associated with this user (if user_type is workshop)
     */
    public function workshop()
    {
        return $this->hasOne(Workshop::class, 'user_id');
    }
}
