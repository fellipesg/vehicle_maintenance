<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Maintenance;
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
        $itemsCreated = 0;
        $warnings = [];

        if ($files === null) {
            return [
                'items_created' => 0,
                'warnings' => [],
            ];
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $result = $this->processSingleFile($maintenance, $file);
            $itemsCreated += $result['items_created'];
            $warnings = array_merge($warnings, $result['warnings']);
        }

        return [
            'items_created' => $itemsCreated,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{items_created: int, warnings: string[]}
     */
    public function processSingleFile(Maintenance $maintenance, UploadedFile $file): array
    {
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('invoices', $fileName, 'public');

        $invoice = Invoice::create([
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => null,
            'invoice_type' => 'general',
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'invoice_number' => null,
            'invoice_date' => null,
            'total_amount' => null,
        ]);

        $parsed = $this->parser->parseStoredPath($filePath);

        if ($parsed) {
            $itemsCreated = $this->syncer->applyToMaintenance($maintenance, $parsed, $invoice);

            if ($itemsCreated === 0 && InvoiceParseFeedback::isPdf($file)) {
                return [
                    'items_created' => 0,
                    'warnings' => [InvoiceParseFeedback::unparsedPdfMessage($file->getClientOriginalName())],
                ];
            }

            return [
                'items_created' => $itemsCreated,
                'warnings' => [],
            ];
        }

        if (InvoiceParseFeedback::isPdf($file)) {
            return [
                'items_created' => 0,
                'warnings' => [InvoiceParseFeedback::unparsedPdfMessage($file->getClientOriginalName())],
            ];
        }

        return [
            'items_created' => 0,
            'warnings' => [],
        ];
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
