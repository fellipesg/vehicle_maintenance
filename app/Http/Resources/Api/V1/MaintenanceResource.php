<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Maintenance */
class MaintenanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'user_id' => $this->user_id,
            'workshop_id' => $this->workshop_id,
            'maintenance_type' => $this->maintenance_type,
            'description' => $this->description,
            'workshop_name' => $this->displayWorkshopName(),
            'maintenance_date' => $this->maintenance_date?->toDateString(),
            'kilometers' => $this->kilometers,
            'service_category' => $this->service_category,
            'is_manufacturer_required' => (bool) $this->is_manufacturer_required,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'user' => new UserResource($this->whenLoaded('user')),
            'workshop' => new WorkshopResource($this->whenLoaded('workshop')),
            'items' => MaintenanceItemResource::collection($this->whenLoaded('items')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'checklists' => ChecklistResource::collection($this->whenLoaded('checklists')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
