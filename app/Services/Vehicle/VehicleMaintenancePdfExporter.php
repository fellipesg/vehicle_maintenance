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
    public function download(Vehicle $vehicle): Response
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
        ]);
        $pdf->setPaper('a4', 'portrait');

        $mainPdfContent = $pdf->output();
        $temps = [];

        try {
            $invoicePdfs = $this->collectInvoicePdfs($vehicle, $temps);
            $content = $invoicePdfs === []
                ? $mainPdfContent
                : $this->mergePdfs($mainPdfContent, $invoicePdfs);
        } finally {
            foreach ($temps as $temp) {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
        }

        $filename = sprintf(
            'historico_manutencoes_%s_%s_%s.pdf',
            $vehicle->license_plate,
            $vehicle->brand,
            now()->format('Y-m-d')
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<string>  $temps
     * @return list<string>
     */
    private function collectInvoicePdfs(Vehicle $vehicle, array &$temps): array
    {
        $paths = [];

        foreach ($vehicle->maintenances as $maintenance) {
            foreach ($maintenance->invoices ?? [] as $invoice) {
                if (! $this->isPdfInvoice($invoice)) {
                    continue;
                }

                $copy = AppStorage::localCopy((string) $invoice->file_path);
                if ($copy === null) {
                    continue;
                }

                if ($copy['temporary']) {
                    $temps[] = $copy['path'];
                }

                $paths[] = $copy['path'];
            }
        }

        return $paths;
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
        $mergedPdf = new Fpdi();
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
