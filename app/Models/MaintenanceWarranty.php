<?php

namespace App\Models;

use App\Enums\WarrantyScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWarranty extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceWarrantyFactory> */
    use HasFactory;

    protected $fillable = [
        'maintenance_id',
        'maintenance_item_id',
        'warranty_template_id',
        'scope',
        'name',
        'body',
        'duration_days',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => WarrantyScope::class,
            'duration_days' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function maintenanceItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceItem::class);
    }

    public function warrantyTemplate(): BelongsTo
    {
        return $this->belongsTo(WarrantyTemplate::class);
    }

    public function isVigente(?CarbonInterface $today = null): bool
    {
        $today ??= now()->startOfDay();

        return $this->ends_at !== null
            && $this->ends_at->greaterThanOrEqualTo($today);
    }

    public function label(): string
    {
        if ($this->isVigente()) {
            return 'Em garantia até '.$this->ends_at->format('d/m/Y');
        }

        return 'Garantia até '.$this->ends_at->format('d/m/Y');
    }

    /**
     * @return array{starts_at: \Carbon\CarbonInterface, ends_at: \Carbon\CarbonInterface}
     */
    public static function computeDates(Maintenance $maintenance, int $durationDays): array
    {
        $startsAt = $maintenance->maintenance_date->copy()->startOfDay();
        $endsAt = $startsAt->copy()->addDays($durationDays);

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    public static function snapshotFromTemplate(WarrantyTemplate $template, Maintenance $maintenance, ?MaintenanceItem $item = null): self
    {
        $dates = self::computeDates($maintenance, $template->duration_days);

        return new self([
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item?->id,
            'warranty_template_id' => $template->id,
            'scope' => $template->scope,
            'name' => $template->name,
            'body' => $template->body,
            'duration_days' => $template->duration_days,
            'starts_at' => $dates['starts_at'],
            'ends_at' => $dates['ends_at'],
        ]);
    }

    public function recomputeDatesFromMaintenance(): void
    {
        if ($this->maintenance === null) {
            $this->load('maintenance');
        }

        $dates = self::computeDates($this->maintenance, $this->duration_days);
        $this->starts_at = $dates['starts_at'];
        $this->ends_at = $dates['ends_at'];
    }
}
