<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePhoto;
use App\Support\Maintenance\MaintenancePhotoFileResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Abre a foto de uma manutenção para quem pode ver a manutenção (MaintenancePhotoPolicy::view),
 * em qualquer portal, como a nota fiscal faz no InvoiceDownloadController. Os portais nunca linkam
 * o caminho do storage: lá a foto é um arquivo público (disco local) ou uma URL assinada
 * compartilhável (S3/R2), e quem tiver o link vê a foto de outra conta — inclusive a de uma OS sem
 * proprietário cujos anexos ainda esperam o aceite dele.
 *
 * O arquivo sai pelo MaintenancePhotoFileResponse, o mesmo que serve a rota da API.
 */
class MaintenancePhotoController extends Controller
{
    public function __invoke(MaintenancePhoto $photo): BinaryFileResponse
    {
        $photo->loadMissing('maintenance');

        Gate::authorize('view', $photo);

        return MaintenancePhotoFileResponse::for($photo);
    }
}
