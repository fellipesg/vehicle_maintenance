<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;

class InvoiceUploadProcessor
{
    public function __construct(
        private readonly InvoiceParser $parser,
        private readonly InvoiceItemSyncer $syncer,
    ) {}

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array{items_created: int, warnings: string[]}
     */
    public function processForMaintenance(Maintenance $maintenance, array|UploadedFile|null $files): array
    {
        $stored = $this->storeUploads($files);

        if ($stored === []) {
            return [
                'items_created' => 0,
                'warnings' => [],
            ];
        }

        $this->createInvoiceRecords($maintenance, $stored);

        return $this->parseStoredUploads($maintenance, $stored);
    }

    /**
     * Upload files to object storage only. Safe to call outside a DB transaction.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return list<array{path: string, original_name: string}>
     */
    public function storeUploads(array|UploadedFile|null $files): array
    {
        if ($files === null) {
            return [];
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $fileName = time().'_'.$file->getClientOriginalName();
            $path = $file->storeAs('invoices', $fileName, AppStorage::diskName());

            if (! is_string($path) || $path === '') {
                continue;
            }

            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return $stored;
    }

    /**
     * Persist invoice rows for already-uploaded files. Call inside the same
     * DB transaction that creates the maintenance, so the FK always holds.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     * @return list<Invoice>
     */
    public function createInvoiceRecords(Maintenance $maintenance, array $stored): array
    {
        $invoices = [];

        foreach ($stored as $file) {
            $invoices[] = Invoice::create([
                'maintenance_id' => $maintenance->id,
                'maintenance_item_id' => null,
                'invoice_type' => 'general',
                'file_path' => $file['path'],
                'file_name' => $file['original_name'],
                'invoice_number' => null,
                'invoice_date' => null,
                'total_amount' => null,
            ]);
        }

        return $invoices;
    }

    /**
     * Parse uploaded invoice files and sync line items. Runs after commit.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     * @return array{items_created: int, warnings: string[]}
     */
    public function parseStoredUploads(Maintenance $maintenance, array $stored): array
    {
        $itemsCreated = 0;
        $warnings = [];

        foreach ($stored as $file) {
            $invoice = $maintenance->invoices()
                ->where('file_path', $file['path'])
                ->first();

            if ($invoice === null) {
                continue;
            }

            $result = $this->processStoredPath(
                $maintenance,
                $invoice,
                $file['path'],
                $file['original_name'],
            );
            $itemsCreated += $result['items_created'];
            $warnings = array_merge($warnings, $result['warnings']);
        }

        return [
            'items_created' => $itemsCreated,
            'warnings' => $warnings,
        ];
    }

    /**
     * Best-effort cleanup when the DB transaction fails after S3 upload.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     */
    public function discardUploads(array $stored): void
    {
        foreach ($stored as $file) {
            try {
                AppStorage::disk()->delete($file['path']);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return array{items_created: int, warnings: string[]}
     */
    public function processSingleFile(Maintenance $maintenance, UploadedFile $file): array
    {
        $stored = $this->storeUploads([$file]);

        if ($stored === []) {
            return [
                'items_created' => 0,
                'warnings' => [],
            ];
        }

        $this->createInvoiceRecords($maintenance, $stored);

        return $this->parseStoredUploads($maintenance, $stored);
    }

    public function processStoredPath(Maintenance $maintenance, Invoice $invoice, string $storagePath, ?string $originalName = null): array
    {
        $parsed = $this->parser->parseStoredPath($storagePath);

        if ($parsed) {
            $itemsCreated = $this->syncer->applyToMaintenance($maintenance, $parsed, $invoice);

            if ($itemsCreated === 0 && InvoiceParseFeedback::isPdf($storagePath)) {
                return [
                    'items_created' => 0,
                    'warnings' => [InvoiceParseFeedback::unparsedPdfMessage($originalName)],
                    'parse_warning' => InvoiceParseFeedback::unparsedPdfMessage($originalName),
                ];
            }

            return [
                'items_created' => $itemsCreated,
                'warnings' => [],
                'parse_warning' => null,
            ];
        }

        if (InvoiceParseFeedback::isPdf($storagePath)) {
            $warning = InvoiceParseFeedback::unparsedPdfMessage($originalName);

            return [
                'items_created' => 0,
                'warnings' => [$warning],
                'parse_warning' => $warning,
            ];
        }

        return [
            'items_created' => 0,
            'warnings' => [],
            'parse_warning' => null,
        ];
    }
}
