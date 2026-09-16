<?php

namespace App\Http\Resources\Api\V1;

use App\Support\VehicleProvenanceStrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicle */
class PublicVehicleSearchResource extends JsonResource
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
            'chassis' => $this->chassis,
            'renavam' => $this->renavam,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'cover_photo_url' => $this->cover_photo_url,
            'cover_photo_portrait_url' => $this->cover_photo_portrait_url,
            'cover_photo_thumb_url' => $this->cover_photo_thumb_url,
            'plate_history' => VehiclePlateResource::collection(
                $this->whenLoaded('plates', fn () => $this->plates->sortByDesc(fn ($p) => $p->started_at ?? $p->created_at)->values())
            ),
            'maintenances_count' => $this->when(isset($this->maintenances_count), $this->maintenances_count),
            'verified_maintenances_count' => $this->when(
                isset($this->verified_maintenances_count),
                $this->verified_maintenances_count
            ),
            'provenance_strip' => $this->when(
                $this->relationLoaded('provenanceStripMaintenances'),
                fn () => VehicleProvenanceStrip::segmentsForVehicle($this->resource)
            ),
            'maintenances' => $this->whenLoaded('maintenances', function () {
                return $this->maintenances->map(fn ($maintenance) => [
                    'id' => $maintenance->id,
                    'maintenance_type' => $maintenance->maintenance_type,
                    'description' => $maintenance->description,
                    'workshop_name' => $maintenance->displayWorkshopName(),
                    'maintenance_date' => $maintenance->maintenance_date?->toDateString(),
                    'kilometers' => $maintenance->kilometers,
                    'service_category' => $maintenance->service_category,
                    'is_manufacturer_required' => (bool) $maintenance->is_manufacturer_required,
                    'photos' => $maintenance->relationLoaded('photos')
                        ? $maintenance->photos
                            ->filter(fn ($photo) => $photo->isPublicVisible())
                            ->map(fn ($photo) => [
                                'id' => $photo->id,
                                'url' => $photo->url,
                                'sort' => $photo->sort,
                            ])->values()->all()
                        : [],
                ])->values()->all();
            }),
        ];
    }
}
