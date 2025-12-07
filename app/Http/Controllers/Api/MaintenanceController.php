<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\Invoice;
use App\Models\Checklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Maintenance::with(['vehicle', 'user', 'items', 'invoices', 'checklists']);

        if ($request->vehicle_id) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'user_id' => 'required|exists:users,id',
            'maintenance_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'workshop_name' => 'nullable|string|max:255',
            'maintenance_date' => 'required|date',
            'kilometers' => 'required|integer|min:0',
            'service_category' => 'required|in:mechanical,electrical,suspension,painting,finishing,interior,other',
            'is_manufacturer_required' => 'boolean',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.part_number' => 'nullable|string|max:100',
            'invoices' => 'nullable|array',
            'invoices.*.file' => 'required_with:invoices|file|mimes:pdf|max:10240',
            'invoices.*.invoice_type' => 'required_with:invoices|in:item,general',
            'invoices.*.maintenance_item_id' => 'nullable|exists:maintenance_items,id',
            'invoices.*.invoice_number' => 'nullable|string|max:100',
            'invoices.*.invoice_date' => 'nullable|date',
            'invoices.*.total_amount' => 'nullable|numeric|min:0',
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

        try {
            DB::beginTransaction();

            $maintenance = Maintenance::create([
                'vehicle_id' => $request->vehicle_id,
                'user_id' => $request->user_id,
                'maintenance_type' => $request->maintenance_type,
                'description' => $request->description,
                'workshop_name' => $request->workshop_name,
                'maintenance_date' => $request->maintenance_date,
                'kilometers' => $request->kilometers,
                'service_category' => $request->service_category,
                'is_manufacturer_required' => $request->is_manufacturer_required ?? false,
            ]);

            // Create maintenance items
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

            // Handle invoice file uploads
            if ($request->has('invoices') && is_array($request->invoices)) {
                foreach ($request->invoices as $invoiceData) {
                    if (isset($invoiceData['file']) && $invoiceData['file']->isValid()) {
                        $file = $invoiceData['file'];
                        $fileName = time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('invoices', $fileName, 'public');

                        Invoice::create([
                            'maintenance_id' => $maintenance->id,
                            'maintenance_item_id' => $invoiceData['maintenance_item_id'] ?? null,
                            'invoice_type' => $invoiceData['invoice_type'],
                            'file_path' => $filePath,
                            'file_name' => $file->getClientOriginalName(),
                            'invoice_number' => $invoiceData['invoice_number'] ?? null,
                            'invoice_date' => $invoiceData['invoice_date'] ?? null,
                            'total_amount' => $invoiceData['total_amount'] ?? null,
                        ]);
                    }
                }
            }

            // Create checklists
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

            $maintenance->load(['items', 'invoices', 'checklists', 'vehicle', 'user']);

            return response()->json([
                'success' => true,
                'data' => $maintenance,
                'message' => 'Maintenance created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating maintenance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $maintenance = Maintenance::with(['vehicle', 'user', 'items', 'invoices', 'checklists'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $maintenance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);

        // Note: In a real scenario, you might want to prevent editing past maintenances
        // For now, we'll allow updates but this should be restricted based on business rules

        $validator = Validator::make($request->all(), [
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

        $maintenance->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $maintenance->fresh(['vehicle', 'user', 'items', 'invoices', 'checklists']),
            'message' => 'Maintenance updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);
        
        // Delete associated invoices files
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
