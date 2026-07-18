<?php

namespace App\Services;

use App\Models\Garage;
use App\Models\Tenant;
use App\Models\User;

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

        return $tenant;
    }
}
