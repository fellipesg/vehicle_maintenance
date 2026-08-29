<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'vehicles' => VehicleResource::collection($this->whenLoaded('currentVehicles')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
