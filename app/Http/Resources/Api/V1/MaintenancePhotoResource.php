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
            'original_name' => $this->original_name,
            // Rota atrás da MaintenancePhotoPolicy::view, nunca AppStorage::url($this->path): no
            // disco público o arquivo abre sem token e, no S3/R2, a URL assinada é compartilhável.
            // O path também sai do contrato, porque com ele o cliente remonta a URL do storage.
            'url' => $this->when(
                $request->user() !== null,
                fn () => route('api.maintenance-photos.show', $this->id),
            ),
            'sort' => $this->sort,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
