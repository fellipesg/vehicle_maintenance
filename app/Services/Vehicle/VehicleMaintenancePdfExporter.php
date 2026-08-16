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
     * @return array{
     *     content: string,
     *     filename: string,
     *     invoices: list<array{filename: string, content: string, mime: string}>,
     *     temps: list<string>
     * }
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

        $temps = [];

        try {
            $pdf = Pdf::loadView('pdfs.vehicle_maintenance_export', [
                'vehicle' => $vehicle,
                'coverImageSrc' => $this->coverImageSrc($vehicle, $temps),
            ]);
            $pdf->setPaper('a4', 'portrait');

            $mainPdfContent = $pdf->output();
            $copies = $this->collectInvoiceCopies($vehicle, $temps);
            $invoicePdfs = array_values(array_filter(
                $copies,
                fn (array $copy): bool => $this->isPdfInvoice($copy['invoice']),
            ));
            $content = $invoicePdfs === []
                ? $mainPdfContent
                : $this->mergePdfs($mainPdfContent, array_column($invoicePdfs, 'path'));
            $invoices = $this->invoiceAttachments($vehicle, $copies);
        } catch (\Throwable $e) {
            $this->cleanupTemps($temps);

            throw $e;
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
            'temps' => $temps,
        ];
    }

    public function download(Vehicle $vehicle): Response
    {
        $file = $this->generate($vehicle);

        try {
            return response($file['content'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            ]);
        } finally {
            $this->cleanupTemps($file['temps']);
        }
    }

    /**
     * @param  list<string>  $temps
     */
    public function cleanupTemps(array $temps): void
    {
        foreach ($temps as $temp) {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    /**
     * DomPDF has enable_remote=false and chroot=base_path(), so S3 URLs and
     * /tmp copies cannot be used as <img src>. Embed JPEG/PNG as a data URI.
     *
     * @param  list<string>  $temps
     */
    private function coverImageSrc(Vehicle $vehicle, array &$temps): ?string
    {
        $path = $vehicle->cover_photo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $copy = AppStorage::localCopy($path);
        if ($copy === null) {
            return null;
        }

        if ($copy['temporary']) {
            $temps[] = $copy['path'];
        }

        $bytes = $copy['content'] ?? (is_file($copy['path']) ? file_get_contents($copy['path']) : false);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            $converted = $this->coverBytesAsJpeg($bytes);
            if ($converted === null) {
                return null;
            }

            $bytes = $converted;
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function coverBytesAsJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        try {
            ob_start();
            imagejpeg($image, null, 85);
            $jpeg = ob_get_clean();

            return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @param  list<string>  $temps
     * @return list<array{invoice: Invoice, path: string, content?: string}>
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
                    'content' => $copy['content'] ?? null,
                ];
            }
        }

        return $copies;
    }

    /**
     * @param  list<array{invoice: Invoice, path: string, content?: string|null}>  $copies
     * @return list<array{filename: string, content: string, mime: string}>
     */
    private function invoiceAttachments(Vehicle $vehicle, array $copies): array
    {
        $attachments = [];
        $usedNames = [];
        $attachedIds = [];

        foreach ($copies as $copy) {
            $content = $copy['content'] ?? null;
            if (! is_string($content) || $content === '') {
                $content = is_file($copy['path']) ? file_get_contents($copy['path']) : false;
            }
            if (! is_string($content) || $content === '') {
                continue;
            }

            $filename = $this->uniqueAttachmentName(
                (string) $copy['invoice']->file_name,
                (string) $copy['invoice']->file_path,
                $usedNames
            );

            $attachments[] = [
                'filename' => $filename,
                'content' => $content,
                'mime' => str_ends_with(strtolower($filename), '.pdf')
                    ? 'application/pdf'
                    : 'application/octet-stream',
            ];
            $attachedIds[$copy['invoice']->id] = true;
        }

        $missing = [];

        foreach ($vehicle->maintenances as $maintenance) {
            foreach ($maintenance->invoices ?? [] as $invoice) {
                if (! isset($attachedIds[$invoice->id]) && (string) $invoice->file_path !== '') {
                    $missing[] = (string) ($invoice->file_name ?: $invoice->file_path);
                }
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'Não foi possível baixar as notas fiscais do storage: '.implode(', ', $missing)
            );
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
