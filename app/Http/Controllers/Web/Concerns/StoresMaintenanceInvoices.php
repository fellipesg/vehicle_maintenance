<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Maintenance;
use App\Services\Invoice\InvoiceItemSyncer;
use App\Services\Invoice\InvoiceParseFeedback;
use App\Services\Invoice\InvoiceParser;
use App\Services\Invoice\InvoiceUploadProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

trait StoresMaintenanceInvoices
{
    protected function prepareInvoiceUploads(Request $request): ?RedirectResponse
    {
        $files = $request->file('invoices');

        if ($files === null) {
            return null;
        }

        $files = is_array($files) ? $files : [$files];
        $validFiles = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (! $file->isValid()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['invoices' => $this->invoiceUploadErrorMessage($file)]);
            }

            $validFiles[] = $file;
        }

        $request->files->set('invoices', $validFiles === [] ? null : $validFiles);

        return null;
    }

    protected function invoiceUploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite de upload do PHP (2 MB no servidor local). Rode o servidor com composer run dev, que já sobe com 20 MB.',
            UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi interrompido. Tente enviar novamente.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Erro no servidor ao receber o arquivo. Verifique permissões da pasta temporária.',
            default => 'Falha ao enviar o arquivo. Selecione o PDF ou XML novamente e tente outra vez.',
        };
    }

    /**
     * Upload invoices to storage, then create the maintenance and invoice rows.
     * Parsing runs afterwards. Avoids a multi-statement DB::transaction on Neon
     * pooler connections, where a swallowed query error aborts the whole block
     * (SQLSTATE 25P02) and hides the real failure.
     *
     * @param  callable(): Maintenance  $createMaintenance
     * @return array{maintenance: Maintenance, items_created: int, items_skipped: int, warnings: string[]}
     */
    protected function storeMaintenanceWithInvoices(Request $request, callable $createMaintenance): array
    {
        $processor = app(InvoiceUploadProcessor::class);
        $stored = $processor->storeUploads($request->file('invoices'));

        try {
            $maintenance = $createMaintenance();
            $processor->createInvoiceRecords($maintenance, $stored);
        } catch (\Throwable $e) {
            $processor->discardUploads($stored);

            throw $e;
        }

        return [
            'maintenance' => $maintenance,
            ...$this->parseMaintenanceInvoices($maintenance, $stored),
        ];
    }

    /**
     * Store, record and parse the invoices sent with an existing OS. Call it
     * after the OS items are saved, so an empty list lets the NF-e fill it.
     *
     * @return array{items_created: int, items_skipped: int, warnings: string[]}
     */
    protected function processMaintenanceInvoices(Request $request, Maintenance $maintenance): array
    {
        $processor = app(InvoiceUploadProcessor::class);
        $stored = $processor->storeUploads($request->file('invoices'));
        $processor->createInvoiceRecords($maintenance, $stored);

        return $this->parseMaintenanceInvoices($maintenance, $stored);
    }

    /**
     * Parse the stored invoices and import their items when the OS has none.
     * `items_skipped` counts NF-e items left out because the OS already had
     * items when parsing started. The unreadable PDF warning only shows when
     * the file really could not be read, never when the import was skipped.
     *
     * @param  list<array{path: string, original_name: string}>  $stored
     * @return array{items_created: int, items_skipped: int, warnings: string[]}
     */
    protected function parseMaintenanceInvoices(Maintenance $maintenance, array $stored): array
    {
        if ($stored === []) {
            return [
                'items_created' => 0,
                'items_skipped' => 0,
                'warnings' => [],
            ];
        }

        $parser = app(InvoiceParser::class);
        $syncer = app(InvoiceItemSyncer::class);
        $hadItemsBeforeImport = $maintenance->items()->exists();

        $itemsCreated = 0;
        $itemsSkipped = 0;
        $warnings = [];

        foreach ($stored as $file) {
            $invoice = $maintenance->invoices()
                ->where('file_path', $file['path'])
                ->first();

            if ($invoice === null) {
                continue;
            }

            $isPdf = InvoiceParseFeedback::isPdf($file['path']);
            $parsed = $parser->parseStoredPath($file['path']);

            if ($parsed === null) {
                if ($isPdf) {
                    $warnings[] = InvoiceParseFeedback::unparsedPdfMessage($file['original_name']);
                }

                continue;
            }

            $result = $syncer->sync($maintenance, $parsed, $invoice);
            $itemsCreated += $result['items_created'];

            if ($result['import_skipped']) {
                if ($hadItemsBeforeImport) {
                    $itemsSkipped += $result['items_skipped'];
                }

                continue;
            }

            if ($result['items_created'] === 0 && $isPdf) {
                $warnings[] = InvoiceParseFeedback::unparsedPdfMessage($file['original_name']);
            }
        }

        return [
            'items_created' => $itemsCreated,
            'items_skipped' => $itemsSkipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  string[]  $warnings
     */
    protected function redirectWithInvoiceFeedback(
        RedirectResponse $redirect,
        int $itemsCreated = 0,
        array $warnings = [],
        int $itemsSkipped = 0,
        string $successMessage = 'Manutenção registrada com sucesso!',
    ): RedirectResponse {
        if ($itemsCreated > 0) {
            $successMessage .= $itemsCreated === 1
                ? ' 1 item importado da NF-e.'
                : " {$itemsCreated} itens importados da NF-e.";
        }

        $redirect = $redirect->with('success', $successMessage);

        if ($itemsSkipped > 0) {
            $redirect = $redirect->with('info', 'Itens da NF-e não importados porque você já informou peças manualmente.');
        }

        if ($warnings !== []) {
            $redirect = $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }
}
