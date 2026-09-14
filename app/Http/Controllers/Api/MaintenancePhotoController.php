<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaintenancePhotoRequest;
use App\Http\Resources\Api\V1\MaintenancePhotoResource;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

#[Group('Maintenance Photos', weight: 13)]
class MaintenancePhotoController extends Controller
{
    public function __construct(
        private MaintenancePhotoService $photos,
    ) {}

    public function store(StoreMaintenancePhotoRequest $request, Maintenance $maintenance): JsonResponse
    {
        $photo = $this->photos->store(
            $maintenance,
            $request->file('photo'),
            $request->string('subject')->toString(),
            $request->string('stage')->toString(),
            $request->user(),
            $request->integer('maintenance_item_id') ?: null,
        );

        return ApiResponse::created(
            new MaintenancePhotoResource($photo),
            'Photo uploaded successfully',
        );
    }

    public function destroy(Maintenance $maintenance, MaintenancePhoto $photo): JsonResponse
    {
        if ($photo->maintenance_id !== $maintenance->id) {
            return ApiResponse::error('Photo does not belong to this maintenance.', 404);
        }

        Gate::authorize('delete', $photo);

        $this->photos->delete($photo);

        return ApiResponse::success(message: 'Photo deleted successfully');
    }
}
