<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;

class TenantService
{
    public function createForUser(User $user): Tenant
    {
        if ($user->tenant_id) {
            return $user->tenant;
        }

        $type = match ($user->user_type) {
            'garage' => 'garage',
            'workshop' => 'workshop',
            default => 'individual',
        };

        $tenant = Tenant::create([
            'type' => $type,
            'name' => $user->name,
        ]);

        $user->update(['tenant_id' => $tenant->id]);

        if ($type === 'garage') {
            Garage::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
            ]);
        }

        if ($type === 'workshop') {
            Workshop::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone ?: '11999999999',
                'email' => $user->email,
                'cep' => '86010000',
                'street' => 'Rua Demonstração',
                'number' => '100',
                'neighborhood' => 'Centro',
                'city' => 'Londrina',
                'state' => 'PR',
            ]);
        }

        return $tenant;
    }
}
