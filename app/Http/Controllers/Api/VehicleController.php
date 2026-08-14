<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Services\VehicleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
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
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::create($validator->validated());
        $user = $request->user();

        $user->vehicles()->attach($vehicle->id, [
            'purchase_date' => $request->purchase_date ?? now(),
            'is_current_owner' => true,
            'tenant_id' => $user->tenant_id,
        ]);

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
            'license_plate' => 'sometimes|required|string|max:10|unique:vehicles,license_plate,' . $id,
            'renavam' => 'sometimes|required|string|max:20|unique:vehicles,renavam,' . $id,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'motorization' => 'nullable|string|max:100',
            'engine' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle->update($validator->validated());

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
            ->with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists', 'maintenances.workshop'])
            ->first();

        if (! $vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vehicle,
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
}
