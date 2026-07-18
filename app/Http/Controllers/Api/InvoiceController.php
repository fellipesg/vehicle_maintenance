<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Rules\InvoiceFile;
use App\Services\Invoice\InvoiceUploadProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * Upload and process invoice PDF or XML, extracting NF-e items when possible.
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', new InvoiceFile, 'max:10240'],
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
            $maintenance = Maintenance::findOrFail($request->maintenance_id);

            if (! $request->user()->can('update', $maintenance)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('invoices', $fileName, 'public');

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

            $result = app(InvoiceUploadProcessor::class)->processStoredPath(
                $maintenance,
                $invoice,
                $filePath,
                $file->getClientOriginalName(),
            );

            $invoice->refresh();

            $response = [
                'success' => true,
                'data' => $invoice->load('maintenance.items'),
                'parsed_items_count' => $result['items_created'],
                'message' => $result['items_created'] > 0
                    ? "Nota fiscal salva e {$result['items_created']} itens importados da NF-e."
                    : 'Nota fiscal salva com sucesso.',
            ];

            if ($result['parse_warning'] ?? null) {
                $response['parse_warning'] = $result['parse_warning'];
            }

            return response()->json($response, 201);
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
            $invoice = Invoice::with('maintenance')->findOrFail($id);
            Gate::authorize('view', $invoice);

            if (! Storage::disk('public')->exists($invoice->file_path)) {
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
        $invoice = Invoice::with('maintenance')->findOrFail($id);
        Gate::authorize('delete', $invoice);

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
