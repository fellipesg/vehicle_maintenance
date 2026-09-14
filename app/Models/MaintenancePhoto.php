<?php

namespace App\Models;

use App\Support\AppStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePhoto extends Model
{
    use HasFactory;

    public const SUBJECT_VEHICLE = 'vehicle';

    public const SUBJECT_PART = 'part';

    public const STAGE_BEFORE = 'before';

    public const STAGE_AFTER = 'after';

    public const STAGE_DURING = 'during';

    public const MAX_PER_GROUP = 4;

    protected $fillable = [
        'maintenance_id',
        'maintenance_item_id',
        'subject',
        'stage',
        'path',
        'original_name',
        'sort',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => AppStorage::url($this->path));
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function maintenanceItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublicVisible(): bool
    {
        return $this->subject === self::SUBJECT_VEHICLE
            && $this->stage === self::STAGE_AFTER;
    }
}
