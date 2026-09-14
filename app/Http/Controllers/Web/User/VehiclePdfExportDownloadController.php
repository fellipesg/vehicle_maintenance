<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehiclePdfExportService;
use App\Support\AppStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehiclePdfExportDownloadController extends Controller
{
    public function __construct(private readonly VehiclePdfExportService $exports) {}

    public function redirectToNamedFile(Request $request, VehiclePdfExport $export): RedirectResponse
    {
        $this->authorizeDownload($request, $export);

        // Relative Location only — redirect()->to() prefixes APP_URL (localhost vs 127.0.0.1).
        return new RedirectResponse(
            '/usuario/exportacoes-pdf/'.$export->id.'/'.$this->exports->resolveDownloadFilename($export),
        );
    }

    public function download(Request $request, VehiclePdfExport $export): BinaryFileResponse
    {
        $this->authorizeDownload($request, $export);

        return $this->exports->downloadFileResponse($export);
    }

    private function authorizeDownload(Request $request, VehiclePdfExport $export): void
    {
        if (! $this->exports->userCanViewExport($request->user(), $export)) {
            abort(403);
        }

        if (! $export->isCompleted() || $export->file_path === null) {
            abort(404);
        }

        if (! AppStorage::disk()->exists($export->file_path)) {
            abort(404);
        }
    }
}
