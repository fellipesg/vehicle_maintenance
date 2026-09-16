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
        $photos = $this->whenLoaded('photos', function () use ($request) {
            $collection = $this->photos;

            if ($this->shouldExposeAllPhotos($request)) {
                return MaintenancePhotoResource::collection($collection);
            }

            return MaintenancePhotoResource::collection(
                $collection->filter(fn ($photo) => $photo->isPublicVisible())->values()
            );
        });

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
            'registered_by_type' => $this->registered_by_type,
            'is_verified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'provenance_label' => $this->provenance_label,
            'provenance_sublabel' => $this->provenance_sublabel,
            'provenance_card_label' => $this->provenance_card_label,
            'provenance_meta' => $this->provenance_meta,
            'invoices_count' => $this->when(isset($this->invoices_count), $this->invoices_count),
            'verified_workshop' => new WorkshopResource($this->whenLoaded('verifiedWorkshop')),
            'verification_code' => $this->when($this->isVerified(), $this->verification_code),
            'verification_url' => $this->when($this->isVerified(), $this->verificationUrl()),
            'verification_qr_matrix' => $this->when($this->isVerified(), fn () => \App\Support\VerificationQr::matrix((string) $this->verificationUrl())),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'user' => new UserResource($this->whenLoaded('user')),
            'workshop' => new WorkshopResource($this->whenLoaded('workshop')),
            'items' => MaintenanceItemResource::collection($this->whenLoaded('items')),
            'general_warranty' => new MaintenanceWarrantyResource($this->whenLoaded('generalWarranty')),
            'warranties' => MaintenanceWarrantyResource::collection($this->whenLoaded('warranties')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'checklists' => ChecklistResource::collection($this->whenLoaded('checklists')),
            'photos' => $photos,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function shouldExposeAllPhotos(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isWorkshop() && $user->workshop) {
            return $this->workshop_id === $user->workshop->id;
        }

        return $this->tenant_id === $user->tenant_id;
    }
}
