<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePhoto;
use App\Support\Maintenance\MaintenancePhotoFileResponse;
use App\Support\Maintenance\MaintenancePhotoUrls;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve uma variante de foto de manutenção quando o disco é local (desenvolvimento e testes). A
 * autorização é a assinatura da URL, emitida por MaintenancePhotoUrls só depois da
 * MaintenancePhotoPolicy::view; a rota vale por uma foto e uma variante e expira em minutos.
 */
class MaintenancePhotoSignedFileController extends Controller
{
    public function __invoke(MaintenancePhoto $photo, string $variant): BinaryFileResponse
    {
        abort_unless(in_array($variant, MaintenancePhotoUrls::VARIANTS, true), 404);

        return MaintenancePhotoFileResponse::for($photo, $variant);
    }
}
