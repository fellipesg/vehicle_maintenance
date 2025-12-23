<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * Upload and process invoice PDF
     * This endpoint will receive a PDF file and attempt to extract data from it
     * TODO: Implement PDF parsing using a library like smalot/pdfparser or similar
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:10240',
            'maintenance_id' => 'required|exists:maintenances,id',
            'maintenance_item_id' => 'nullable|exists:maintenance_items,id',
            'invoice_type' => 'required|in:item,general',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('invoices', $fileName, 'public');

            // TODO: Implement PDF parsing to extract invoice data
            // For now, we'll just store the file
            $invoice = Invoice::create([
                'maintenance_id' => $request->maintenance_id,
                'maintenance_item_id' => $request->maintenance_item_id,
                'invoice_type' => $request->invoice_type,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'invoice_number' => $request->invoice_number ?? null,
                'invoice_date' => $request->invoice_date ?? null,
                'total_amount' => $request->total_amount ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $invoice,
                'message' => 'Invoice uploaded successfully. PDF parsing will be implemented.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download invoice file
     */
    public function download(string $id): StreamedResponse|JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($id);

            if (!Storage::disk('public')->exists($invoice->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice file not found',
                ], 404);
            }

            return Storage::disk('public')->download($invoice->file_path, $invoice->file_name);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete invoice
     */
    public function destroy(string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (Storage::disk('public')->exists($invoice->file_path)) {
            Storage::disk('public')->delete($invoice->file_path);
        }

        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully',
        ]);
    }
}
