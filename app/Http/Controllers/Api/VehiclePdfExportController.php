<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehiclePdfExportResource;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehiclePdfExportService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
