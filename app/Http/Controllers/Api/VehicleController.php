<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

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
            ->with(['maintenances.items', 'maintenances.invoices', 'maintenances.checklists', 'maintenances.workshop'])
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
            ->with(['items', 'invoices', 'checklists', 'user', 'workshop'])
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
    public function exportPdf(string $id)
    {
        try {
            $vehicle = Vehicle::with([
                'maintenances.items',
                'maintenances.invoices',
                'maintenances.checklists',
                'maintenances.user',
                'maintenances.workshop'
            ])
                ->findOrFail($id);

            // Order maintenances by date (most recent first)
            $vehicle->maintenances = $vehicle->maintenances->sortByDesc('maintenance_date')->values();

            // Generate the main PDF from the Blade template
            $pdf = Pdf::loadView('pdfs.vehicle_maintenance_export', [
                'vehicle' => $vehicle,
            ]);

            // Set PDF options
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('enable-local-file-access', true);

            // Get the main PDF content
            $mainPdfContent = $pdf->output();

            // Collect all invoice PDFs
            $invoicePdfs = [];
            foreach ($vehicle->maintenances as $maintenance) {
                if ($maintenance->invoices && $maintenance->invoices->count() > 0) {
                    foreach ($maintenance->invoices as $invoice) {
                        $invoicePath = Storage::disk('public')->path($invoice->file_path);
                        if (file_exists($invoicePath)) {
                            $invoicePdfs[] = $invoicePath;
                        }
                    }
                }
            }

            // If there are invoice PDFs, merge them with the main PDF
            if (count($invoicePdfs) > 0) {
                $mergedPdf = new Fpdi();
                
                // Save main PDF to temp file for FPDI
                $tempMainPdf = tempnam(sys_get_temp_dir(), 'main_pdf_');
                file_put_contents($tempMainPdf, $mainPdfContent);
                
                // Import the main PDF
                $pageCount = $mergedPdf->setSourceFile($tempMainPdf);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $mergedPdf->AddPage();
                    $tplId = $mergedPdf->importPage($i);
                    $mergedPdf->useTemplate($tplId);
                }
                
                // Clean up temp file
                unlink($tempMainPdf);

                // Import and append invoice PDFs
                foreach ($invoicePdfs as $invoicePath) {
                    try {
                        $invoicePageCount = $mergedPdf->setSourceFile($invoicePath);
                        
                        for ($i = 1; $i <= $invoicePageCount; $i++) {
                            $mergedPdf->AddPage();
                            $tplId = $mergedPdf->importPage($i);
                            $mergedPdf->useTemplate($tplId);
                        }
                    } catch (\Exception $e) {
                        // Skip invoice if it can't be merged (corrupted or incompatible)
                        continue;
                    }
                }

                $finalPdfContent = $mergedPdf->Output('', 'S');
            } else {
                // No invoices to merge, use main PDF as is
                $finalPdfContent = $mainPdfContent;
            }

            // Generate filename
            $filename = sprintf(
                'historico_manutencoes_%s_%s_%s.pdf',
                $vehicle->license_plate,
                $vehicle->brand,
                now()->format('Y-m-d')
            );

            // Return PDF as download
            return response($finalPdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar PDF: ' . $e->getMessage(),
            ], 500);
        }
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
