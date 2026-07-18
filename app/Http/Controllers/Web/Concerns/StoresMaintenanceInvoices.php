<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Maintenance;
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite de upload do PHP (2 MB no servidor local). Reinicie com: php -d upload_max_filesize=20M -d post_max_size=25M artisan serve --port=8000',
            UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi interrompido. Tente enviar novamente.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Erro no servidor ao receber o arquivo. Verifique permissões da pasta temporária.',
            default => 'Falha ao enviar o arquivo. Selecione o PDF ou XML novamente e tente outra vez.',
        };
    }

    /**
     * @return array{items_created: int, warnings: string[]}
     */
    protected function processMaintenanceInvoices(Request $request, Maintenance $maintenance): array
    {
        return app(InvoiceUploadProcessor::class)
            ->processForMaintenance($maintenance, $request->file('invoices'));
    }

    /**
     * @return string[]
     */
    protected function storeMaintenanceInvoices(Request $request, Maintenance $maintenance): array
    {
        return $this->processMaintenanceInvoices($request, $maintenance)['warnings'];
    }

    protected function redirectWithInvoiceFeedback($redirect, int $itemsCreated = 0, array $warnings = [])
    {
        if ($itemsCreated > 0) {
            $redirect = $redirect->with(
                'success',
                "Manutenção registrada com sucesso! {$itemsCreated} itens importados da NF-e."
            );
        } else {
            $redirect = $redirect->with('success', 'Manutenção registrada com sucesso!');
        }

        if ($warnings !== []) {
            $redirect = $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }
}
