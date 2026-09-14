<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MaintenanceWarranty */
class MaintenanceWarrantyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_id' => $this->maintenance_id,
            'maintenance_item_id' => $this->maintenance_item_id,
            'warranty_template_id' => $this->warranty_template_id,
            'scope' => $this->scope?->value,
            'name' => $this->name,
            'body' => $this->body,
            'duration_days' => $this->duration_days,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'label' => $this->label(),
            'is_vigente' => $this->isVigente(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
