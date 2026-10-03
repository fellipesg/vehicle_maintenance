<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\Vehicle\VehicleIdentifierVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'workshop_id' => $this->when($this->isWorkshop(), fn () => $this->workshop?->id),
            'tenant_id' => $this->tenant_id,
            'is_admin' => (bool) $this->is_admin,
            'phone' => $this->phone,
            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'subscription_active' => (bool) $this->subscription_active,
            'has_two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'vehicles' => $this->whenLoaded('currentVehicles', fn (): Collection => $this->currentVehiclesFor($request)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Os veículos de que a conta é dona atual. Quem os vê é o usuário da requisição ou, no login e
     * no cadastro (requisição ainda sem usuário), a própria conta que acabou de entrar: para ela
     * chassi e RENAVAM saem inteiros.
     *
     * @return Collection<int, VehicleResource>
     */
    private function currentVehiclesFor(Request $request): Collection
    {
        /** @var User $viewer */
        $viewer = VehicleIdentifierVisibility::viewerOf($request) ?? $this->resource;

        return $this->currentVehicles->map(
            fn (Vehicle $vehicle): VehicleResource => (new VehicleResource($vehicle))->viewedBy($viewer),
        );
    }
}
