<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workshop;

class WorkshopPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Workshop $workshop): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isWorkshop() && $user->tenant_id !== null;
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $workshop->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $workshop->tenant_id === $user->tenant_id;
    }
}
