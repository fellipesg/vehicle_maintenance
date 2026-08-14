<?php

namespace App\Services\Vehicle;

use App\Models\Invoice;
use App\Models\Vehicle;
use App\Support\AppStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\Response;

class VehicleMaintenancePdfExporter
{
    /**
     * @return array{content: string, filename: string, invoices: list<array{filename: string, content: string, mime: string}>}
     */
    public function generate(Vehicle $vehicle): array
    {
        $vehicle->load([
            'maintenances.items',
            'maintenances.invoices',
            'maintenances.checklists',
            'maintenances.user',
            'maintenances.workshop',
        ]);

        $vehicle->setRelation(
            'maintenances',
            $vehicle->maintenances->sortByDesc('maintenance_date')->values()
        );

        $pdf = Pdf::loadView('pdfs.vehicle_maintenance_export', [
            'vehicle' => $vehicle,
            'invoiceDownloadUrls' => $this->invoiceDownloadUrls($vehicle),
        ]);
        $pdf->setPaper('a4', 'portrait');

        $mainPdfContent = $pdf->output();
        $temps = [];
        $invoices = [];

        try {
            $copies = $this->collectInvoiceCopies($vehicle, $temps);
            $invoicePdfs = array_values(array_filter(
                $copies,
                fn (array $copy): bool => $this->isPdfInvoice($copy['invoice']),
            ));
            $content = $invoicePdfs === []
                ? $mainPdfContent
                : $this->mergePdfs($mainPdfContent, array_column($invoicePdfs, 'path'));
            $invoices = $this->attachmentsFromCopies($copies);
        } finally {
            foreach ($temps as $temp) {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
        }

        return [
            'content' => $content,
            'filename' => sprintf(
                'historico_manutencoes_%s_%s_%s.pdf',
                $vehicle->license_plate,
                $vehicle->brand,
                now()->format('Y-m-d')
            ),
            'invoices' => $invoices,
        ];
    }

    public function download(Vehicle $vehicle): Response
    {
        $file = $this->generate($vehicle);

        return response($file['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function invoiceDownloadUrls(Vehicle $vehicle): array
    {
        if (! AppStorage::isRemote()) {
            return [];
        }

        $urls = [];

        foreach ($vehicle->maintenances as $maintenance) {
            foreach ($maintenance->invoices ?? [] as $invoice) {
                try {
                    $urls[$invoice->id] = AppStorage::url((string) $invoice->file_path, now()->addDays(6));
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $urls;
    }

    /**
     * @param  list<string>  $temps
     * @return list<array{invoice: Invoice, path: string}>
     */
    private function collectInvoiceCopies(Vehicle $vehicle, array &$temps): array
    {
        $copies = [];

        foreach ($vehicle->maintenances as $maintenance) {
            foreach ($maintenance->invoices ?? [] as $invoice) {
                $copy = AppStorage::localCopy((string) $invoice->file_path);
                if ($copy === null) {
                    continue;
                }

                if ($copy['temporary']) {
                    $temps[] = $copy['path'];
                }

                $copies[] = [
                    'invoice' => $invoice,
                    'path' => $copy['path'],
                ];
            }
        }

        return $copies;
    }

    /**
     * @param  list<array{invoice: Invoice, path: string}>  $copies
     * @return list<array{filename: string, content: string, mime: string}>
     */
    private function attachmentsFromCopies(array $copies): array
    {
        $attachments = [];
        $usedNames = [];

        foreach ($copies as $copy) {
            $content = file_get_contents($copy['path']);
            if (! is_string($content) || $content === '') {
                continue;
            }

            $filename = $this->uniqueAttachmentName((string) $copy['invoice']->file_name, $copy['path'], $usedNames);
            $attachments[] = [
                'filename' => $filename,
                'content' => $content,
                'mime' => str_ends_with(strtolower($filename), '.pdf')
                    ? 'application/pdf'
                    : 'application/octet-stream',
            ];
        }

        return $attachments;
    }

    /**
     * @param  list<string>  $usedNames
     */
    private function uniqueAttachmentName(string $fileName, string $path, array &$usedNames): string
    {
        $name = $fileName !== '' ? $fileName : basename($path);
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'nota';
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $i = 2;

        while (in_array($candidate, $usedNames, true)) {
            $candidate = $extension === ''
                ? $base.'-'.$i
                : $base.'-'.$i.'.'.$extension;
            $i++;
        }

        $usedNames[] = $candidate;

        return $candidate;
    }

    private function isPdfInvoice(Invoice $invoice): bool
    {
        $path = strtolower((string) $invoice->file_path);
        $name = strtolower((string) $invoice->file_name);

        return str_ends_with($path, '.pdf') || str_ends_with($name, '.pdf');
    }

    /**
     * @param  list<string>  $invoicePaths
     */
    private function mergePdfs(string $mainPdfContent, array $invoicePaths): string
    {
        $mergedPdf = new Fpdi;
        $tempMainPdf = tempnam(sys_get_temp_dir(), 'main_pdf_');
        file_put_contents($tempMainPdf, $mainPdfContent);

        try {
            $pageCount = $mergedPdf->setSourceFile($tempMainPdf);
            for ($i = 1; $i <= $pageCount; $i++) {
                $mergedPdf->AddPage();
                $tplId = $mergedPdf->importPage($i);
                $mergedPdf->useTemplate($tplId);
            }

            foreach ($invoicePaths as $invoicePath) {
                try {
                    $invoicePageCount = $mergedPdf->setSourceFile($invoicePath);
                    for ($i = 1; $i <= $invoicePageCount; $i++) {
                        $mergedPdf->AddPage();
                        $tplId = $mergedPdf->importPage($i);
                        $mergedPdf->useTemplate($tplId);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $mergedPdf->Output('', 'S');
        } finally {
            if (is_file($tempMainPdf)) {
                unlink($tempMainPdf);
            }
        }
    }
}
