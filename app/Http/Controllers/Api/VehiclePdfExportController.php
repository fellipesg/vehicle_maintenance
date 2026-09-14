<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehiclePdfExportResource;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehiclePdfExportService;
use App\Support\ApiResponse;
use App\Support\AppStorage;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Vehicle PDF Exports', weight: 11)]
class VehiclePdfExportController extends Controller
{
    public function __construct(private readonly VehiclePdfExportService $exports) {}

    #[Endpoint(
        title: 'Get vehicle PDF export status',
        description: 'Poll every 2 seconds until status is completed or failed. download_url is only present when completed.',
    )]
    public function show(Request $request, string $exportId): JsonResponse
    {
        $export = VehiclePdfExport::query()->findOrFail($exportId);

        if (! $this->exports->userCanViewExport($request->user(), $export)) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success(
            new VehiclePdfExportResource($this->exports->statusPayload($export)),
        );
    }

    #[Endpoint(
        title: 'Download vehicle PDF export',
        description: 'Streams the generated PDF with Content-Disposition attachment. Use this URL instead of download_url when you need a forced file download in browsers.',
    )]
    public function download(Request $request, string $exportId): BinaryFileResponse|JsonResponse
    {
        $export = VehiclePdfExport::query()->findOrFail($exportId);

        if (! $this->exports->userCanViewExport($request->user(), $export)) {
            return ApiResponse::error('Forbidden', 403);
        }

        if (! $export->isCompleted() || $export->file_path === null) {
            return ApiResponse::error('PDF export is not ready for download', 404);
        }

        if (! AppStorage::disk()->exists($export->file_path)) {
            return ApiResponse::error('PDF file not found', 404);
        }

        return $this->exports->downloadFileResponse($export);
    }
}
