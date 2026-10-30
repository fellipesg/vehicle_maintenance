<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaintenancePhotoRequest;
use App\Http\Resources\Api\V1\MaintenancePhotoResource;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Support\ApiResponse;
use App\Support\Maintenance\MaintenancePhotoFileResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /**
     * Abre o arquivo da foto para quem pode ver a manutenção, como GET /api/v1/invoices/{id}/download
     * faz com a nota fiscal. O MaintenancePhotoResource devolve esta rota, nunca o caminho no
     * storage: lá a foto é um arquivo público (disco local) ou uma URL assinada compartilhável
     * (S3/R2), e quem tiver o link vê a foto de outra conta — inclusive a de uma OS sem
     * proprietário cujos anexos ainda esperam o aceite dele.
     */
    #[Endpoint(
        title: 'Download maintenance photo',
        description: 'Serves the photo file itself. Images (jpg, jpeg, png, webp) are sent inline; any other extension is sent as an attachment. Requires `maintenances:read` and passing MaintenancePhotoPolicy::view.',
    )]
    public function show(MaintenancePhoto $photo): BinaryFileResponse
    {
        $photo->loadMissing('maintenance');

        Gate::authorize('view', $photo);

        return MaintenancePhotoFileResponse::for($photo);
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
