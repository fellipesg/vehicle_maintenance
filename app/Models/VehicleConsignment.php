<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vehicle a garage holds for sale on behalf of its owner.
 *
 * The garage may register maintenances from the moment it declares the consignment,
 * but reading the pre-existing history stays locked until the owner approves it or
 * staff approve an uploaded power of attorney.
 */
class VehicleConsignment extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleConsignmentFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const HISTORY_NONE = 'none';

    public const HISTORY_PENDING = 'pending';

    public const HISTORY_APPROVED = 'approved';

    public const HISTORY_REJECTED = 'rejected';

    protected $fillable = [
        'vehicle_id',
        'garage_user_id',
        'tenant_id',
        'owner_user_id',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_document',
        'declaration_accepted_at',
        'declaration_ip',
        'declaration_user_agent',
        'power_of_attorney_path',
        'history_access_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'status',
        'started_at',
        'ended_at',
        'end_reason',
        'owner_notified_at',
        'owner_dispute_token',
    ];

    protected function casts(): array
    {
        return [
            'declaration_accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'owner_notified_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function garageUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garage_user_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<VehicleConsignment>  $query
     * @return Builder<VehicleConsignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function grantsHistoryAccess(): bool
    {
        return $this->isActive() && $this->history_access_status === self::HISTORY_APPROVED;
    }

    public function isHistoryReviewPending(): bool
    {
        return $this->history_access_status === self::HISTORY_PENDING;
    }
}
