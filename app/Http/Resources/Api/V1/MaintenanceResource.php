<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Maintenance\MaintenanceRedactor;
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
        // OS de oficina sem proprietário: forma mínima (sem descrição, valores, garantias, notas e
        // fotos) para quem não é a oficina autora. Cópia em memória, nada é gravado.
        $maintenance = MaintenanceRedactor::redact($this->resource, $request->user());
        $isOwnerless = $maintenance->isOwnerlessRecord();

        $photos = $this->whenLoaded('photos', function () use ($request, $maintenance) {
            $collection = $maintenance->photos;

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
            'description' => $maintenance->description,
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
            'invoices_count' => $this->when(isset($maintenance->invoices_count), $maintenance->invoices_count),
            'is_ownerless_record' => $isOwnerless,
            'owner_status' => $maintenance->owner_status,
            'attachments_status' => $isOwnerless ? $maintenance->attachments_status : null,
            'hidden_from_public' => $maintenance->isHiddenFromPublic(),
            $this->mergeWhen(
                $isOwnerless && $this->resource->isCreatedByWorkshopOf($request->user()),
                fn () => [
                    'whatsapp_invited_at' => $this->resource->invite?->whatsapp_invited_at?->toIso8601String(),
                    'email_invited_at' => $this->resource->invite?->email_invited_at?->toIso8601String(),
                ],
            ),
            'verified_workshop' => new WorkshopResource($this->whenLoaded('verifiedWorkshop')),
            'verification_code' => $this->when($this->isVerified(), $this->verification_code),
            'verification_url' => $this->when($this->isVerified(), $this->verificationUrl()),
            'verification_qr_matrix' => $this->when($this->isVerified(), fn () => \App\Support\VerificationQr::matrix((string) $this->verificationUrl())),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'user' => new UserResource($this->whenLoaded('user')),
            'workshop' => new WorkshopResource($this->whenLoaded('workshop')),
            'items' => MaintenanceItemResource::collection($this->when($maintenance->relationLoaded('items'), fn () => $maintenance->items)),
            'general_warranty' => new MaintenanceWarrantyResource($this->when($maintenance->relationLoaded('generalWarranty'), fn () => $maintenance->generalWarranty)),
            'warranties' => MaintenanceWarrantyResource::collection($this->when($maintenance->relationLoaded('warranties'), fn () => $maintenance->warranties)),
            'invoices' => InvoiceResource::collection($this->when($maintenance->relationLoaded('invoices'), fn () => $maintenance->invoices)),
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
