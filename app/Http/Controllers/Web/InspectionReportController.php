<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use App\Support\AppStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Abre o PDF do laudo de uma vistoria cautelar, em qualquer portal, para quem passa na
 * VehicleInspectionPolicy::viewReport — a mesma pergunta de VehicleInspection::showsFullReportTo,
 * que é a usada pela redação (App\Support\Inspection\InspectionRedactor).
 *
 * As duas têm de concordar: a forma mínima tira o PDF da tela, e uma rota de arquivo mais frouxa
 * devolveria por download o que a tela escondeu. Nunca linke o caminho do storage — no disco local o
 * arquivo abre sem login, e no S3/R2 a URL assinada é compartilhável (.ai/rules/views.md).
 */
class InspectionReportController extends Controller
{
    public function __invoke(VehicleInspection $inspection): BinaryFileResponse
    {
        $inspection->loadMissing('vehicle');

        Gate::authorize('viewReport', $inspection);

        $path = (string) $inspection->report_path;

        if ($path === '') {
            abort(404);
        }

        $copy = AppStorage::localCopy($path);

        if ($copy === null) {
            abort(404);
        }

        $response = response()->download(
            $copy['path'],
            $this->downloadName($inspection, $path),
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            ],
            ResponseHeaderBag::DISPOSITION_INLINE,
        );

        // O BinaryFileResponse do response()->download() nasce público (e um Cache-Control
        // 'private' no array de cabeçalhos é descartado), então o cache privado é marcado aqui.
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->addCacheControlDirective('no-store');

        if ($copy['temporary']) {
            $response->deleteFileAfterSend();
        }

        return $response;
    }

    /**
     * O nome que a oficina enviou, sem o que o Content-Disposition não aceita (separadores de
     * caminho). O caminho no storage é aleatório, então ele serve só para exibir.
     */
    private function downloadName(VehicleInspection $inspection, string $path): string
    {
        $name = trim(str_replace(['/', '\\'], '', (string) $inspection->report_original_name));

        return $name !== '' ? $name : basename($path);
    }
}
