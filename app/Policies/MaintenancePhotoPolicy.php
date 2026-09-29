<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;

/**
 * Enviar ou apagar foto altera a manutenção: segue a MaintenancePolicy::update (dono atual na
 * declarada, oficina na OS com o selo dela).
 */
class MaintenancePhotoPolicy
{
    public function create(User $user, Maintenance $maintenance): bool
    {
        return app(MaintenancePolicy::class)->update($user, $maintenance)->allowed();
    }

    public function delete(User $user, MaintenancePhoto $photo): bool
    {
        return app(MaintenancePolicy::class)->update($user, $photo->maintenance)->allowed();
    }
}
