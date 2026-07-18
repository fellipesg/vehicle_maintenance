<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\Checklist;
use App\Rules\InvoiceFile;
use App\Services\Invoice\InvoiceUploadProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Maintenance::class);

        $user = $request->user();
        $query = Maintenance::with(['vehicle', 'user', 'items', 'invoices', 'checklists', 'workshop']);

        if ($user->isWorkshop() && $user->workshop) {
            $query->where(function ($q) use ($user) {
                $q->where('tenant_id', $user->tenant_id)
                    ->orWhere('workshop_id', $user->workshop->id);
            });
        } else {
            $query->where('tenant_id', $user->tenant_id);
        }

        if ($request->vehicle_id) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->service_category) {
            $query->where('service_category', $request->service_category);
        }

        $maintenances = $query->orderBy('maintenance_date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $maintenances,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Maintenance::class);

        $user = $request->user();
        $userId = $user->id;

        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'workshop_id' => 'nullable|exists:workshops,id',
            'maintenance_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'required|date',
            'kilometers' => 'nullable|integer|min:0',
            'service_category' => 'required|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'is_manufacturer_required' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.part_number' => 'nullable|string|max:100',
            'invoices' => 'nullable|array',
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'checklists' => 'nullable|array',
            'checklists.*.checklist_type' => 'required_with:checklists|in:initial,final',
            'checklists.*.items' => 'required_with:checklists|array',
            'checklists.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = \App\Models\Vehicle::findOrFail($request->vehicle_id);
        Gate::authorize('view', $vehicle);

        try {
            DB::beginTransaction();

            $isManufacturerRequired = false;
            if ($request->has('is_manufacturer_required')) {
                $value = $request->is_manufacturer_required;
                if (is_string($value)) {
                    $isManufacturerRequired = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                } elseif (is_int($value)) {
                    $isManufacturerRequired = $value === 1;
                } else {
                    $isManufacturerRequired = (bool) $value;
                }
            }

            $workshopName = $request->workshop_name;
            if ($request->workshop_id) {
                $workshop = \App\Models\Workshop::find($request->workshop_id);
                if ($workshop) {
                    $workshopName = $workshop->name;
                }
            }

            $maintenance = Maintenance::create([
                'vehicle_id' => $request->vehicle_id,
                'user_id' => $userId,
                'tenant_id' => $user->tenant_id,
                'workshop_id' => $request->workshop_id,
                'maintenance_type' => $request->maintenance_type,
                'description' => $request->description,
                'workshop_name' => $workshopName,
                'maintenance_date' => $request->maintenance_date,
                'kilometers' => $request->kilometers ?? null,
                'service_category' => $request->service_category,
                'is_manufacturer_required' => $isManufacturerRequired,
            ]);

            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $itemData) {
                    MaintenanceItem::create([
                        'maintenance_id' => $maintenance->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'] ?? null,
                        'total_price' => $itemData['total_price'] ?? null,
                        'part_number' => $itemData['part_number'] ?? null,
                    ]);
                }
            }

            $uploadResult = ['items_created' => 0, 'warnings' => []];

            $invoiceFiles = $request->file('invoices');
            if ($invoiceFiles) {
                $uploadResult = app(InvoiceUploadProcessor::class)->processForMaintenance($maintenance, $invoiceFiles);
            }

            if ($request->has('checklists') && is_array($request->checklists)) {
                foreach ($request->checklists as $checklistData) {
                    Checklist::create([
                        'maintenance_id' => $maintenance->id,
                        'checklist_type' => $checklistData['checklist_type'],
                        'items' => $checklistData['items'],
                        'notes' => $checklistData['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $maintenance->load(['items', 'invoices', 'checklists', 'vehicle', 'user', 'workshop']);

            $response = [
                'success' => true,
                'data' => $maintenance,
                'message' => 'Maintenance created successfully',
            ];

            if ($uploadResult['items_created'] > 0) {
                $response['parsed_items_count'] = $uploadResult['items_created'];
            }

            if ($uploadResult['warnings'] !== []) {
                $response['parse_warnings'] = $uploadResult['warnings'];
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating maintenance: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $maintenance = Maintenance::with(['vehicle', 'user', 'items', 'invoices', 'checklists', 'workshop'])
            ->findOrFail($id);

        Gate::authorize('view', $maintenance);

        return response()->json([
            'success' => true,
            'data' => $maintenance,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);
        Gate::authorize('update', $maintenance);

        $validator = Validator::make($request->all(), [
            'workshop_id' => 'nullable|exists:workshops,id',
            'maintenance_type' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'sometimes|required|date',
            'kilometers' => 'sometimes|required|integer|min:0',
            'service_category' => 'sometimes|required|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'is_manufacturer_required' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['workshop_id'])) {
            $workshop = \App\Models\Workshop::find($data['workshop_id']);
            if ($workshop) {
                $data['workshop_name'] = $workshop->name;
            }
        }

        $maintenance->update($data);

        return response()->json([
            'success' => true,
            'data' => $maintenance->fresh(['vehicle', 'user', 'items', 'invoices', 'checklists', 'workshop']),
            'message' => 'Maintenance updated successfully',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);
        Gate::authorize('delete', $maintenance);

        foreach ($maintenance->invoices as $invoice) {
            if (Storage::disk('public')->exists($invoice->file_path)) {
                Storage::disk('public')->delete($invoice->file_path);
            }
        }

        $maintenance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance deleted successfully',
        ]);
    }
}
