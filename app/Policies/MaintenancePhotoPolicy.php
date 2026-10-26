<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Support\Maintenance\MaintenancePhotoAccess;

/**
 * Ver a foto segue quem pode ver a manutenção, como a InvoicePolicy::view. Enviar ou apagar foto
 * altera a manutenção: segue a MaintenancePolicy::update (dono atual na declarada, oficina na OS
 * com o selo dela).
 *
 * Antes de tudo vem o recurso: conta sem as fotos das manutenções ligadas pelo admin
 * (MaintenancePhotoAccess) não vê, não envia e não apaga foto. As fotos já guardadas ficam intactas.
 */
class MaintenancePhotoPolicy
{
    public function view(User $user, MaintenancePhoto $photo): bool
    {
        if (! MaintenancePhotoAccess::allows($user)) {
            return false;
        }

        $maintenance = $photo->maintenance;

        if (! $maintenance) {
            return false;
        }

        // Foto de OS sem proprietário: só a oficina autora até o aceite do proprietário.
        if ($maintenance->hidesAttachmentsFrom($user)) {
            return false;
        }

        return app(MaintenancePolicy::class)->view($user, $maintenance);
    }

    public function create(User $user, Maintenance $maintenance): bool
    {
        if (! MaintenancePhotoAccess::allows($user)) {
            return false;
        }

        return app(MaintenancePolicy::class)->update($user, $maintenance)->allowed();
    }

    public function delete(User $user, MaintenancePhoto $photo): bool
    {
        if (! MaintenancePhotoAccess::allows($user)) {
            return false;
        }

        return app(MaintenancePolicy::class)->update($user, $photo->maintenance)->allowed();
    }
}
