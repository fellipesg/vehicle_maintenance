<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use App\Support\Vehicle\VehicleIdentifierMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registro de oficina feito antes do proprietário (GET /me/workshop-records). Só nomes de itens e
 * contagens de anexos: descrição livre, valores e arquivos não saem daqui.
 *
 * @mixin Maintenance
 */
class WorkshopRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->vehicle;
        $workshop = $this->verifiedWorkshop ?? $this->workshop;
        $pendingAttachments = $this->attachments_status === Maintenance::ATTACHMENTS_PENDING;
        $decisions = app(MaintenanceOwnerDecisionService::class);

        return [
            'id' => $this->id,
            'vehicle' => [
                'id' => $vehicle->id,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis_masked' => VehicleIdentifierMask::chassis($vehicle->chassis),
            ],
            'workshop' => [
                'id' => $workshop?->id,
                'name' => $this->displayWorkshopName(),
            ],
            'maintenance_date' => $this->maintenance_date?->toDateString(),
            'kilometers' => (int) $this->kilometers,
            'service_category' => $this->service_category,
            'maintenance_type' => $this->maintenance_type,
            'items' => $this->items->map(fn ($item): array => [
                'name' => $item->name,
                'quantity' => $item->quantity !== null ? (float) $item->quantity : null,
            ])->values()->all(),
            'verification_code' => $this->verification_code,
            'attachments' => [
                'invoices' => $pendingAttachments ? $this->invoices->count() : 0,
                'photos' => $pendingAttachments ? $this->photos->count() : 0,
            ],
            'owner_status' => $this->owner_status,
            'attachments_status' => $this->attachments_status,
            'hidden_from_public' => $this->isHiddenFromPublic(),
            'can_decide' => $request->user() !== null && $decisions->canDecide($request->user(), $this->resource),
            'can_accept_attachments' => $request->user() !== null && $decisions->canAcceptAttachments($request->user(), $this->resource),
        ];
    }
}
