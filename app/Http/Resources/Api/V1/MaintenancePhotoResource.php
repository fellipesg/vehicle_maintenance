<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MaintenancePhoto */
class MaintenancePhotoResource extends JsonResource
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
            'subject' => $this->subject,
            'stage' => $this->stage,
            'path' => $this->path,
            'original_name' => $this->original_name,
            'url' => $this->url,
            'sort' => $this->sort,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
