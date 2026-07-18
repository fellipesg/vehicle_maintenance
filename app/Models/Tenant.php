<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function garage(): HasOne
    {
        return $this->hasOne(Garage::class);
    }

    public function workshop(): HasOne
    {
        return $this->hasOne(Workshop::class);
    }

    public function isIndividual(): bool
    {
        return $this->type === 'individual';
    }

    public function isGarage(): bool
    {
        return $this->type === 'garage';
    }

    public function isWorkshop(): bool
    {
        return $this->type === 'workshop';
    }
}
