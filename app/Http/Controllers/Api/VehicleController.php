<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverService;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\VehicleTimelineBuilder;
use App\Services\VehicleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleCoverService $covers) {}

    public function catalogBrands(VehicleCatalogService $catalog): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $catalog->brands(),
        ]);
    }

    public function catalogModels(Request $request, VehicleCatalogService $catalog): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $catalog->models($request->query('brand', '')),
        ]);
    }

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
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Vehicle::class);

        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:10|unique:vehicles,license_plate',
            'renavam' => 'required|string|max:20|unique:vehicles,renavam',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'required|integer|min:0|max:9999999',
            'terms_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = collect($validator->validated())
            ->except(['terms_accepted'])
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

        return response()->json([
            'success' => true,
            'data' => $vehicle,
            'message' => 'Vehicle created successfully',
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists'])
            ->findOrFail($id);

        Gate::authorize('view', $vehicle);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('update', $vehicle);

        $validator = Validator::make($request->all(), [
            'license_plate' => 'sometimes|required|string|max:10|unique:vehicles,license_plate,'.$id,
            'renavam' => 'sometimes|required|string|max:20|unique:vehicles,renavam,'.$id,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:'.(date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
            'current_kilometers' => 'sometimes|required|integer|min:0|max:9999999',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle->update($validator->validated());

        if ($request->has('current_kilometers')) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        }

        return response()->json([
            'success' => true,
            'data' => $vehicle->fresh(),
            'message' => 'Vehicle updated successfully',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('delete', $vehicle);

        $request->user()->vehicles()->detach($vehicle->id);

        if (! $vehicle->maintenances()->exists()) {
            $vehicle->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle removed from your account',
        ]);
    }

    public function search(string $identifier): JsonResponse
    {
        $vehicle = Vehicle::where('license_plate', $identifier)
            ->orWhere('renavam', $identifier)
            ->with(['maintenances' => function ($query) {
                $query->orderBy('maintenance_date', 'desc');
            }, 'maintenances.workshop'])
            ->first();

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->publicVehicleSearchPayload($vehicle),
        ]);
    }

    public function maintenances(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('viewMaintenances', $vehicle);

        $maintenances = $vehicle->maintenances()
            ->with(['items', 'invoices', 'checklists', 'user', 'workshop'])
            ->orderBy('maintenance_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $maintenances,
        ]);
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('view', $vehicle);

        return response()->json([
            'success' => true,
            'data' => app(VehicleTimelineBuilder::class)->build($vehicle),
        ]);
    }

    public function exportPdf(Request $request, string $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('view', $vehicle);

        try {
            return app(VehicleMaintenancePdfExporter::class)->download($vehicle);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function myVehicles(Request $request): JsonResponse
    {
        $user = $request->user();

        $vehicles = $user->currentVehicles()
            ->with(['maintenances' => function ($query) {
                $query->orderBy('maintenance_date', 'desc');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Vehicle linked to user successfully',
            'data' => $vehicle->fresh(),
        ]);
    }

    public function uploadCover(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        Gate::authorize('update', $vehicle);

        $validator = Validator::make($request->all(), [
            'cover' => [
                'required',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('cover');
        $vehicle = $this->covers->store($vehicle, $file);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
            'message' => 'Cover photo uploaded successfully',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicVehicleSearchPayload(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'renavam' => $vehicle->renavam,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'maintenances' => $vehicle->maintenances->map(function ($maintenance) {
                return [
                    'id' => $maintenance->id,
                    'maintenance_type' => $maintenance->maintenance_type,
                    'description' => $maintenance->description,
                    'workshop_name' => $maintenance->displayWorkshopName(),
                    'maintenance_date' => $maintenance->maintenance_date,
                    'kilometers' => $maintenance->kilometers,
                    'service_category' => $maintenance->service_category,
                    'is_manufacturer_required' => $maintenance->is_manufacturer_required,
                ];
            })->values()->all(),
        ];
    }
}
