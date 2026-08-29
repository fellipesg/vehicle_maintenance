<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Services\Invoice\InvoiceUploadProcessor;
use App\Support\ApiResponse;
use App\Support\AppStorage;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Invoices', weight: 18)]
class InvoiceController extends Controller
{
    /**
     * Upload and process invoice PDF or XML, extracting NF-e items when possible.
     */
    #[Endpoint(
        title: 'Upload invoice',
        description: 'Multipart form upload. Required fields: `file` (PDF or XML, max 10 MB), `maintenance_id`, `invoice_type` (`item` or `general`). Optional: `maintenance_item_id`, `invoice_number`, `invoice_date`, `total_amount`.',
    )]
    public function upload(UploadInvoiceRequest $request): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($request->maintenance_id);

        Gate::authorize('update', $maintenance);

        try {
            $file = $request->file('file');
            $fileName = time().'_'.$file->getClientOriginalName();
            $filePath = $file->storeAs('invoices', $fileName, AppStorage::diskName());

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

            $payload = [
                'success' => true,
                'data' => new InvoiceResource($invoice->load('maintenance.items')),
                'parsed_items_count' => $result['items_created'],
                'message' => $result['items_created'] > 0
                    ? "Invoice saved and {$result['items_created']} items imported from NF-e."
                    : 'Invoice saved successfully.',
            ];

            if ($result['parse_warning'] ?? null) {
                $payload['parse_warning'] = $result['parse_warning'];
            }

            return response()->json($payload, 201);
        } catch (\Exception $e) {
            Log::error('Error uploading invoice', ['exception' => $e->getMessage()]);

            return ApiResponse::error(
                app()->hasDebugModeEnabled()
                    ? 'Error uploading invoice: '.$e->getMessage()
                    : 'Unable to upload invoice.',
                500,
            );
        }
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        try {
            $invoice = Invoice::with('maintenance')->findOrFail($id);
            Gate::authorize('view', $invoice);

            if (! AppStorage::disk()->exists($invoice->file_path)) {
                return ApiResponse::error('Invoice file not found', 404);
            }

            return AppStorage::disk()->download($invoice->file_path, $invoice->file_name);
        } catch (\Exception $e) {
            Log::error('Error downloading invoice', ['exception' => $e->getMessage()]);

            return ApiResponse::error(
                app()->hasDebugModeEnabled()
                    ? 'Error downloading invoice: '.$e->getMessage()
                    : 'Unable to download invoice.',
                500,
            );
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $invoice = Invoice::with('maintenance')->findOrFail($id);
        Gate::authorize('delete', $invoice);

        if (AppStorage::disk()->exists($invoice->file_path)) {
            AppStorage::disk()->delete($invoice->file_path);
        }

        $invoice->delete();

        return ApiResponse::success(message: 'Invoice deleted successfully');
    }
}
