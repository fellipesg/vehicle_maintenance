<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MaintenanceItem */
class MaintenanceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $warrantyLoaded = $this->relationLoaded('warranty');

        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'part_number' => $this->part_number,
            'has_warranty' => $warrantyLoaded ? $this->warranty !== null : null,
            'warranty_starts_at' => $warrantyLoaded ? $this->warranty?->starts_at?->format('Y-m-d') : null,
            'warranty_ends_at' => $warrantyLoaded ? $this->warranty?->ends_at?->format('Y-m-d') : null,
            'warranty_period_label' => $warrantyLoaded ? $this->warrantyPeriodLabel() : null,
            'is_under_warranty' => $warrantyLoaded ? $this->isUnderWarranty() : null,
            'warranty' => new MaintenanceWarrantyResource($this->whenLoaded('warranty')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
