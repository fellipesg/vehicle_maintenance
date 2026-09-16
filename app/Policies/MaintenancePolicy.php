<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;

class MaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Maintenance $maintenance): bool
    {
        if ($user->isWorkshop() && $user->workshop) {
            return $maintenance->workshop_id === $user->workshop->id;
        }

        return $maintenance->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Maintenance $maintenance): bool
    {
        if ($maintenance->isVerified() && ! ($user->isWorkshop() && $user->workshop && $maintenance->workshop_id === $user->workshop->id)) {
            return false;
        }

        if ($user->isWorkshop() && $user->workshop) {
            return $maintenance->workshop_id === $user->workshop->id;
        }

        return $maintenance->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Maintenance $maintenance): bool
    {
        if ($maintenance->isVerified() && ! ($user->isWorkshop() && $user->workshop && $maintenance->workshop_id === $user->workshop->id)) {
            return false;
        }

        if ($user->isWorkshop() && $user->workshop) {
            return $maintenance->workshop_id === $user->workshop->id;
        }

        return $maintenance->tenant_id === $user->tenant_id;
    }
}
