<?php

namespace App\Http\Resources\Api\V1;

use App\Support\VehicleProvenanceStrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicle */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_plate' => $this->license_plate,
            'current_plate' => $this->license_plate,
            'renavam' => $this->renavam,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'chassis' => $this->chassis,
            'motorization' => $this->motorization,
            'engine' => $this->engine,
            'current_kilometers' => $this->current_kilometers,
            'odometer_at_registration' => $this->odometer_at_registration,
            'cover_photo_url' => $this->cover_photo_url,
            'cover_photo_portrait_url' => $this->cover_photo_portrait_url,
            'cover_photo_thumb_url' => $this->cover_photo_thumb_url,
            'maintenances_count' => $this->when(isset($this->maintenances_count), $this->maintenances_count),
            'verified_maintenances_count' => $this->when(
                isset($this->verified_maintenances_count),
                $this->verified_maintenances_count
            ),
            'provenance_strip' => $this->when(
                $this->relationLoaded('provenanceStripMaintenances'),
                fn () => VehicleProvenanceStrip::segmentsForVehicle($this->resource)
            ),
            'plate_history' => VehiclePlateResource::collection(
                $this->whenLoaded('plates', fn () => $this->plates->sortByDesc(fn ($p) => $p->started_at ?? $p->created_at)->values())
            ),
            'maintenances' => MaintenanceResource::collection($this->whenLoaded('maintenances')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
