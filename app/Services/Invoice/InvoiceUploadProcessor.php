<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class InvoiceUploadProcessor
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'xml'];

    public function __construct(
        private readonly InvoiceParser $parser,
        private readonly InvoiceItemSyncer $syncer,
    ) {}

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array{items_created: int, warnings: string[], failed: list<string>}
     */
    public function processForMaintenance(Maintenance $maintenance, array|UploadedFile|null $files): array
    {
        $uploads = $this->storeUploads($files);

        if ($uploads['stored'] === []) {
            return [
                'items_created' => 0,
                'warnings' => [],
                'failed' => $uploads['failed'],
            ];
        }

        $records = $this->createInvoiceRecordsOrDiscard($maintenance, $uploads['stored']);

        return [
            ...$this->parseStoredUploads($maintenance, $uploads['stored']),
            'failed' => [...$uploads['failed'], ...$records['failed']],
        ];
    }

    /**
     * Upload files to object storage only. Safe to call outside a DB transaction.
     *
     * `failed` carries the original name of every file the storage refused. The
     * app disks run with `throw => false`, so a failed write comes back as a
     * plain `false` from `storeAs`: without this list the upload would vanish
     * with no exception, no log line and nothing to tell the user.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array{stored: list<array{path: string, original_name: string}>, failed: list<string>}
     */
    public function storeUploads(array|UploadedFile|null $files): array
    {
        if ($files === null) {
            return ['stored' => [], 'failed' => []];
        }

        if (! is_array($files)) {
            $files = [$files];
        }

        $stored = [];
        $failed = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $this->storeFile($file);

            if ($path === null) {
                $failed[] = $file->getClientOriginalName();
                report(new \RuntimeException(
                    'Falha ao gravar a nota fiscal no storage: '.$file->getClientOriginalName(),
                ));

                continue;
            }

            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return ['stored' => $stored, 'failed' => $failed];
    }

    /**
     * Store an invoice under a random name; the client file name never reaches the path.
     */
    public function storeFile(UploadedFile $file): ?string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = strtolower((string) $file->guessExtension());
        }

        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';
        $fileName = Str::random(40).($extension !== '' ? '.'.$extension : '');
        $path = $file->storeAs('invoices', $fileName, AppStorage::diskName());

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Persist invoice rows for already-uploaded files. Call inside the same
     * DB transaction that creates the maintenance, so the FK always holds.
     *
     * In a transaction the rollback removes every row, so the caller discards every
     * uploaded file (Api/MaintenanceController::store does that). Outside one, use
     * createInvoiceRecordsOrDiscard instead: there the rows before the failure are
     * already committed and their files must stay.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     * @return list<Invoice>
     */
    public function createInvoiceRecords(Maintenance $maintenance, array $stored): array
    {
        $invoices = [];

        foreach ($stored as $file) {
            $invoices[] = $this->createInvoiceRecord($maintenance, $file);
        }

        return $invoices;
    }

    /**
     * Persist the invoice rows and, when one of them fails, delete only the files
     * that were left without a row and report which notas did not make it.
     *
     * There is no transaction around these inserts (see the note in
     * StoresMaintenanceInvoices about the Neon pooler), so the rows created before
     * the failure are already committed. Deleting their files too would leave a nota
     * pointing at nothing — worse than the orphan object this guards against, because
     * the download route can only answer "Invoice file not found" from then on.
     *
     * It does not rethrow: the OS and the notas before the failure are committed, and
     * a 500 here would tell the user that nothing was saved, which is how a duplicate
     * OS gets created on the retry. The failure is reported and the caller turns
     * `failed` into a message naming the files to send again.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     * @return array{invoices: list<Invoice>, failed: list<string>}
     */
    public function createInvoiceRecordsOrDiscard(Maintenance $maintenance, array $stored): array
    {
        $invoices = [];

        foreach ($stored as $index => $file) {
            try {
                $invoices[] = $this->createInvoiceRecord($maintenance, $file);
            } catch (\Throwable $e) {
                report($e);

                // A primeira falha vale pelas seguintes: o insert quebrou por algo da conexão
                // ou da tabela, e insistir nas próximas só repetiria o erro.
                $leftOut = array_slice($stored, $index);
                $this->discardUploads($leftOut);

                return [
                    'invoices' => $invoices,
                    'failed' => array_column($leftOut, 'original_name'),
                ];
            }
        }

        return ['invoices' => $invoices, 'failed' => []];
    }

    /**
     * @param  array{path: string, original_name: string}  $file
     */
    private function createInvoiceRecord(Maintenance $maintenance, array $file): Invoice
    {
        return Invoice::create([
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
            $this->discardUpload($file['path']);
        }
    }

    /**
     * Best-effort cleanup of a single uploaded file. Without it, a failed insert
     * leaves the object in S3/R2 orphaned: paid for and referenced by no row.
     */
    public function discardUpload(string $path): void
    {
        try {
            AppStorage::disk()->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{items_created: int, warnings: string[], failed: list<string>}
     */
    public function processSingleFile(Maintenance $maintenance, UploadedFile $file): array
    {
        return $this->processForMaintenance($maintenance, [$file]);
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
