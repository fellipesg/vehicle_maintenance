<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::with('maintenances')
            ->when($request->search, function ($query, $search) {
                return $query->where('license_plate', 'like', "%{$search}%")
                    ->orWhere('renavam', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:10|unique:vehicles,license_plate',
            'renavam' => 'required|string|max:20|unique:vehicles,renavam',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
            'engine' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::create($validator->validated());

        // Automatically link vehicle to authenticated user
        if ($request->user()) {
            $request->user()->vehicles()->attach($vehicle->id, [
                'purchase_date' => $request->purchase_date ?? now(),
                'is_current_owner' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $vehicle,
            'message' => 'Vehicle created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $vehicle = Vehicle::with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'license_plate' => 'sometimes|required|string|max:10|unique:vehicles,license_plate,' . $id,
            'renavam' => 'sometimes|required|string|max:20|unique:vehicles,renavam,' . $id,
            'brand' => 'sometimes|required|string|max:100',
            'model' => 'sometimes|required|string|max:100',
            'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'chassis' => 'nullable|string|max:50',
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
        ]);
    }

    /**
     * Search vehicle by license plate or RENAVAM
     */
    public function search(string $identifier): JsonResponse
    {
        $vehicle = Vehicle::where('license_plate', $identifier)
            ->orWhere('renavam', $identifier)
            ->with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists'])
            ->first();

        if (!$vehicle) {
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

    /**
     * Get all maintenances for a vehicle
     */
    public function maintenances(string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $maintenances = $vehicle->maintenances()
            ->with(['items', 'invoices', 'checklists', 'user'])
            ->orderBy('maintenance_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $maintenances,
        ]);
    }

    /**
     * Export vehicle maintenance history to PDF
     */
    public function exportPdf(string $id): JsonResponse
    {
        $vehicle = Vehicle::with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists', 'maintenances.user'])
            ->findOrFail($id);

        // TODO: Implement PDF generation using a library like dompdf or barryvdh/laravel-dompdf
        // For now, return the data that would be used for PDF generation
        return response()->json([
            'success' => true,
            'message' => 'PDF export functionality will be implemented',
            'data' => $vehicle,
        ]);
    }

    /**
     * Get all vehicles owned by authenticated user
     */
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

    /**
     * Link a vehicle to the authenticated user
     */
    public function linkToUser(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);
        $user = $request->user();

        // Check if vehicle is already linked to user
        $existingLink = $user->vehicles()->where('vehicle_id', $vehicle->id)->first();

        if ($existingLink) {
            // Update existing link to set as current owner
            $user->vehicles()->updateExistingPivot($vehicle->id, [
                'is_current_owner' => true,
                'purchase_date' => $request->purchase_date ?? now(),
            ]);
        } else {
            // Create new link
            $user->vehicles()->attach($vehicle->id, [
                'purchase_date' => $request->purchase_date ?? now(),
                'is_current_owner' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle linked to user successfully',
            'data' => $vehicle->fresh(),
        ]);
    }
}
