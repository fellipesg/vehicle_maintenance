<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\Vehicle\VehicleIdentifierVisibility;
use App\Support\VehicleProvenanceStrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Veículo nas respostas da API. Chassi e RENAVAM saem inteiros só para o dono atual
 * (VehiclePolicy::update); lojista em consignação, oficina, dono anterior e admin recebem os
 * números parciais, com identifiers_masked = true (App\Support\Vehicle\VehicleIdentifierVisibility).
 *
 * @mixin \App\Models\Vehicle
 */
class VehicleResource extends JsonResource
{
    private ?User $viewer = null;

    /**
     * Quem vê o veículo, quando a requisição ainda não tem usuário (ex.: a resposta do login, que
     * devolve os veículos da conta que acabou de entrar). Sem isso vale o usuário da requisição.
     */
    public function viewedBy(User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identifiers = VehicleIdentifierVisibility::fields(
            $this->viewer ?? VehicleIdentifierVisibility::viewerOf($request),
            $this->resource,
        );

        return [
            'id' => $this->id,
            'license_plate' => $this->license_plate,
            'current_plate' => $this->license_plate,
            'renavam' => $identifiers['renavam'],
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'chassis' => $identifiers['chassis'],
            'identifiers_masked' => $identifiers['identifiers_masked'],
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
