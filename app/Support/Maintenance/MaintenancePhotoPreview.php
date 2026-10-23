<?php

namespace App\Support\Maintenance;

use App\Models\MaintenancePhoto;
use Illuminate\Support\Collection;

/**
 * Quais fotos aparecem na linha do tempo de uma OS (no máximo 2): a primeira de veículo/antes e a
 * primeira de veículo/depois, pela ordem (sort); sem o par, as duas primeiras de veículo/depois. Peça
 * e "durante" ficam só no detalhe. Recebe só as fotos que o leitor pode ver.
 */
final class MaintenancePhotoPreview
{
    public const LIMIT = 2;

    /**
     * @param  Collection<int, MaintenancePhoto>  $visiblePhotos
     * @return Collection<int, MaintenancePhoto>
     */
    public static function pick(Collection $visiblePhotos): Collection
    {
        $vehiclePhotos = $visiblePhotos
            ->where('subject', MaintenancePhoto::SUBJECT_VEHICLE)
            ->sortBy([['sort', 'asc'], ['id', 'asc']]);

        $before = $vehiclePhotos->firstWhere('stage', MaintenancePhoto::STAGE_BEFORE);
        $after = $vehiclePhotos->where('stage', MaintenancePhoto::STAGE_AFTER)->values();

        if ($before !== null && $after->isNotEmpty()) {
            return collect([$before, $after->first()]);
        }

        return $after->take(self::LIMIT)->values();
    }
}
