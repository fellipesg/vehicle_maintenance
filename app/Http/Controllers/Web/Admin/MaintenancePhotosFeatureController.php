<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\UpdateMaintenancePhotosFeatureRequest;
use App\Models\Garage;
use App\Models\User;
use App\Models\Workshop;
use App\Support\Maintenance\MaintenancePhotoAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Liga as fotos das manutenções (App\Features\MaintenancePhotos, Pennant) em um usuário, uma
 * oficina ou uma loja. Desligada é o padrão; quem decide se um usuário tem a funcionalidade é a
 * soma dos três (App\Support\Maintenance\MaintenancePhotoAccess). Desligar não apaga foto: elas
 * ficam guardadas e voltam a aparecer quando o recurso for religado.
 */
class MaintenancePhotosFeatureController extends Controller
{
    public function user(UpdateMaintenancePhotosFeatureRequest $request, User $user): RedirectResponse
    {
        return $this->toggle($request, $user, 'a conta de '.$user->name);
    }

    public function workshop(UpdateMaintenancePhotosFeatureRequest $request, Workshop $workshop): RedirectResponse
    {
        return $this->toggle($request, $workshop, 'a oficina '.$workshop->name);
    }

    public function garage(UpdateMaintenancePhotosFeatureRequest $request, Garage $garage): RedirectResponse
    {
        return $this->toggle($request, $garage, 'a loja '.$garage->name);
    }

    /**
     * Grava o valor e registra quem mudou (admin, alvo e novo valor) no log da aplicação; a tabela
     * features do Pennant guarda só o estado atual e a data.
     */
    private function toggle(UpdateMaintenancePhotosFeatureRequest $request, Model $scope, string $subject): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $changed = MaintenancePhotoAccess::isActiveFor($scope) !== $enabled;

        MaintenancePhotoAccess::set($scope, $enabled);

        if ($changed) {
            Log::info('Fotos das manutenções '.($enabled ? 'ligadas' : 'desligadas').' por um admin.', [
                'admin_id' => $request->user()->id,
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
                'enabled' => $enabled,
            ]);
        }

        return back()->with('success', 'Fotos das manutenções '.($enabled ? 'ligadas' : 'desligadas').' para '.$subject.'.');
    }
}
