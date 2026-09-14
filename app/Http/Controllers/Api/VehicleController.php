<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVehicleRequest;
use App\Http\Requests\Api\V1\UpdateVehicleRequest;
use App\Http\Requests\Api\V1\UploadVehicleCoverRequest;
use App\Http\Resources\Api\V1\MaintenanceResource;
use App\Http\Resources\Api\V1\PublicVehicleSearchResource;
use App\Http\Resources\Api\V1\TimelineResource;
use App\Http\Resources\Api\V1\VehiclePdfExportResource;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\VehiclePdfExportService;
use App\Services\Vehicle\VehicleTimelineBuilder;
use App\Services\VehicleCatalogService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Vehicles', weight: 10)]
class VehicleController extends Controller
{
    use ResolvesPagination;

    public function __construct(private readonly VehicleCoverService $covers) {}

    #[QueryParameter('limit', 'Maximum number of brands to return (default 500, max 500).', type: 'integer')]
    #[Endpoint(title: 'Vehicle catalog brands')]
    public function catalogBrands(Request $request, VehicleCatalogService $catalog): JsonResponse
    {
        $brands = $catalog->brands();
        $limit = $this->catalogLimit($request);

        return ApiResponse::success(array_slice($brands, 0, $limit));
    }

    #[QueryParameter('brand', 'Filter models by brand name.', required: true)]
    #[QueryParameter('limit', 'Maximum number of models to return (default 500, max 500).', type: 'integer')]
    #[Endpoint(title: 'Vehicle catalog models')]
    public function catalogModels(Request $request, VehicleCatalogService $catalog): JsonResponse
    {
        $models = $catalog->models($request->query('brand', ''));
        $limit = $this->catalogLimit($request);

        return ApiResponse::success(array_slice($models, 0, $limit));
    }

    #[QueryParameter('search', 'Filter by license plate, RENAVAM, brand, or model.')]
    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $vehicles = $request->user()->currentVehicles()
            ->withCount('maintenances')
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('license_plate', 'like', "%{$search}%")
                        ->orWhere('renavam', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($vehicles, VehicleResource::class);
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $data = collect($request->validated())
            ->except(['terms_accepted', 'purchase_date'])
            ->all();

        $vehicle = Vehicle::create($data);
        $user = $request->user();

        $user->vehicles()->attach($vehicle->id, [
            'purchase_date' => $request->purchase_date ?? now(),
            'is_current_owner' => true,
            'tenant_id' => $user->tenant_id,
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.terms_version'),
        ]);

        app(VehicleMileageService::class)->registerOdometer(
            $vehicle,
            (int) $data['current_kilometers'],
        );
        $vehicle->refresh();

        return ApiResponse::created(new VehicleResource($vehicle), 'Vehicle created successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::withCount('maintenances')->findOrFail($id);

        Gate::authorize('view', $vehicle);

        return ApiResponse::success(new VehicleResource($vehicle));
    }

    public function update(UpdateVehicleRequest $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $vehicle->update($request->validated());

        if ($request->has('current_kilometers')) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        }

        return ApiResponse::success(new VehicleResource($vehicle->fresh()), 'Vehicle updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('delete', $vehicle);

        $request->user()->vehicles()->detach($vehicle->id);

        if (! $vehicle->maintenances()->exists()) {
            $vehicle->delete();
        }

        return ApiResponse::success(message: 'Vehicle removed from your account');
    }

    /**
     * Public vehicle search by license plate or RENAVAM.
     *
     * No authentication required. Response excludes owner PII (no user data, addresses, or contact info).
     */
    #[Group('Vehicles (Public)', weight: 5)]
    #[Endpoint(
        title: 'Search vehicle (public)',
        description: 'Public endpoint. Returns maintenance history without owner PII.',
    )]
    public function search(string $identifier): JsonResponse
    {
        $vehicle = Vehicle::where('license_plate', $identifier)
            ->orWhere('renavam', $identifier)
            ->with(['maintenances' => function ($query) {
                $query->orderBy('maintenance_date', 'desc')
                    ->with(['photos' => fn ($photos) => $photos
                        ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
                        ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)
                        ->orderBy('sort'),
                    ]);
            }, 'maintenances.workshop'])
            ->first();

        if (! $vehicle) {
            return ApiResponse::error('Vehicle not found', 404);
        }

        return ApiResponse::success(new PublicVehicleSearchResource($vehicle));
    }

    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    public function maintenances(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('viewMaintenances', $vehicle);

        $maintenances = $vehicle->maintenances()
            ->with(['items.warranty', 'generalWarranty', 'invoices', 'checklists', 'user', 'workshop'])
            ->orderBy('maintenance_date', 'desc')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($maintenances, MaintenanceResource::class);
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('view', $vehicle);

        return ApiResponse::success(
            new TimelineResource(app(VehicleTimelineBuilder::class)->build($vehicle)),
        );
    }

    #[Endpoint(
        title: 'Request maintenance history PDF export',
        description: 'Queues async PDF generation. Poll status_url until completed, then download via the signed download_url.',
    )]
    public function requestExportPdf(Request $request, string $id, VehiclePdfExportService $exports): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $export = $exports->createExport($request->user(), $vehicle);

        return ApiResponse::success(
            new VehiclePdfExportResource($exports->queuedPayload($export)),
            'PDF export queued.',
            202,
        );
    }

    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    public function myVehicles(Request $request): JsonResponse
    {
        $user = $request->user();

        $vehicles = $user->currentVehicles()
            ->withCount('maintenances')
            ->orderByDesc('vehicles.created_at')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($vehicles, VehicleResource::class);
    }

    public function linkToUser(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('link', $vehicle);

        $user = $request->user();

        $existingLink = $user->vehicles()->where('vehicle_id', $vehicle->id)->first();

        if ($existingLink) {
            $user->vehicles()->updateExistingPivot($vehicle->id, [
                'is_current_owner' => true,
                'purchase_date' => $request->purchase_date ?? now(),
                'tenant_id' => $user->tenant_id,
            ]);
        } else {
            $user->vehicles()->attach($vehicle->id, [
                'purchase_date' => $request->purchase_date ?? now(),
                'is_current_owner' => true,
                'tenant_id' => $user->tenant_id,
            ]);
        }

        return ApiResponse::success(new VehicleResource($vehicle->fresh()), 'Vehicle linked to user successfully');
    }

    #[Endpoint(
        title: 'Upload vehicle cover',
        description: 'Multipart form upload. Fields: `cover` (landscape 16:9) and/or `cover_portrait` (portrait 9:16). At least one required. Image: jpg, jpeg, png, webp; max 5 MB each.',
    )]
    public function uploadCover(UploadVehicleCoverRequest $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        if ($request->hasFile('cover')) {
            $vehicle = $this->covers->storeLandscape($vehicle, $request->file('cover'));
        }

        if ($request->hasFile('cover_portrait')) {
            $vehicle = $this->covers->storePortrait($vehicle, $request->file('cover_portrait'));
        }

        return ApiResponse::success(new VehicleResource($vehicle->fresh()), 'Cover photo uploaded successfully');
    }
}
