<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'whatsapp',
        'email',
        'facebook',
        'instagram',
        'cep',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * Get the user (owner) of this workshop
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all maintenances for this workshop
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Get all items for this workshop
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get all services for this workshop
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get full address as a single string
     */
    public function getFullAddressAttribute(): string
    {
        $address = "{$this->street}, {$this->number}";
        if ($this->complement) {
            $address .= " - {$this->complement}";
        }
        $address .= " - {$this->neighborhood}, {$this->city}/{$this->state}";
        $address .= " - CEP: {$this->cep}";
        return $address;
    }

    /**
     * Get formatted CEP
     */
    public function getFormattedCepAttribute(): string
    {
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $this->cep);
    }
}
