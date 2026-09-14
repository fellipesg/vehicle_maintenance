<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;

class MaintenancePhotoPolicy
{
    public function create(User $user, Maintenance $maintenance): bool
    {
        return app(MaintenancePolicy::class)->update($user, $maintenance);
    }

    public function delete(User $user, MaintenancePhoto $photo): bool
    {
        return app(MaintenancePolicy::class)->update($user, $photo->maintenance);
    }
}
