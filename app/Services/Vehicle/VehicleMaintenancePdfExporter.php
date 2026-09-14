<?php

namespace App\Services\Vehicle;

use App\Models\Invoice;
use App\Models\Vehicle;
use App\Support\AppStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\Response;

class VehicleMaintenancePdfExporter
{
    public const COVER_CROP_WIDTH = 760;

    public const COVER_CROP_HEIGHT = 228;

    public const WORKSHOP_LOGO_CROP_WIDTH = 120;

    public const WORKSHOP_LOGO_CROP_HEIGHT = 130;

    public const WORKSHOP_LOGO_DISPLAY_WIDTH = 120;

    public const WORKSHOP_LOGO_DISPLAY_HEIGHT = 130;

    public const WORKSHOP_LOGO_EMBED_SCALE = 2;

    public const LETTERHEAD_JPEG_QUALITY = 92;

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
            'maintenances.items.warranty',
            'maintenances.generalWarranty',
            'maintenances.invoices',
            'maintenances.checklists',
            'maintenances.user',
            'maintenances.workshop',
            'maintenances.photos' => fn ($query) => $query
                ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
                ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)
                ->orderBy('sort'),
        ]);

        $vehicle->setRelation(
            'maintenances',
            $vehicle->maintenances->sortByDesc('maintenance_date')->values()
        );

        $temps = [];

        try {
            $pathsToFetch = $this->collectAssetPaths($vehicle);
            $copies = AppStorage::localCopies($pathsToFetch);

            $workshopLogos = [];
            foreach ($vehicle->maintenances as $maintenance) {
                if ($maintenance->workshop_id === null) {
                    continue;
                }

                $logoPath = $maintenance->workshop?->logo_path;
                if (! is_string($logoPath) || $logoPath === '') {
                    continue;
                }

                $workshopLogos[$maintenance->id] = $this->workshopLogoSrcFromCopy(
                    $copies[$logoPath] ?? null,
                    $temps,
                );
            }

            $coverPath = $vehicle->coverPathForPdf();
            $coverImageSrc = is_string($coverPath) && $coverPath !== ''
                ? $this->coverImageSrcFromCopy($copies[$coverPath] ?? null, $temps)
                : null;

            $pdf = Pdf::loadView('pdfs.vehicle_maintenance_export', [
                'vehicle' => $vehicle,
                'coverImageSrc' => $coverImageSrc,
                'workshopLogos' => $workshopLogos,
                'workshopLogoWidth' => self::WORKSHOP_LOGO_DISPLAY_WIDTH,
                'workshopLogoHeight' => self::WORKSHOP_LOGO_DISPLAY_HEIGHT,
                'revisalogCoverLogoSrc' => $this->revisalogBrandPath('lockup-horizontal-tagline.png'),
                'revisalogLogoSrc' => $this->revisalogLetterheadLogoSrc(),
            ]);
            $pdf->setPaper('a4', 'portrait');

            $mainPdfContent = $pdf->output();
            $invoiceCopies = $this->invoiceCopiesFromPrefetched($vehicle, $copies, $temps);
            $invoicePdfs = array_values(array_filter(
                $invoiceCopies,
                fn (array $copy): bool => $this->isPdfInvoice($copy['invoice']),
            ));
            $content = $invoicePdfs === []
                ? $mainPdfContent
                : $this->mergePdfs($mainPdfContent, array_column($invoicePdfs, 'path'));
            $invoices = $this->invoiceAttachments($vehicle, $invoiceCopies);
        } catch (\Throwable $e) {
            $this->cleanupTemps($temps);

            throw $e;
        }

        return [
            'content' => $content,
            'filename' => $this->downloadFilename($vehicle),
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
                'Content-Disposition' => $this->attachmentDisposition($file['filename']),
            ]);
        } finally {
            $this->cleanupTemps($file['temps']);
        }
    }

    public function downloadFilename(Vehicle $vehicle): string
    {
        $base = sprintf(
            'historico_manutencoes_%s_%s_%s',
            $vehicle->license_plate,
            $vehicle->brand,
            now()->format('Y-m-d'),
        );

        $ascii = Str::of($base)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '_')
            ->trim('_')
            ->value();

        if ($ascii === '') {
            $ascii = 'historico_manutencoes_'.now()->format('Y-m-d');
        }

        return $ascii.'.pdf';
    }

    public function attachmentDisposition(string $filename): string
    {
        $safe = str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';

        return 'attachment; filename="'.$safe.'"; filename*=UTF-8\'\''.rawurlencode($safe);
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
     * @return list<string>
     */
    private function collectAssetPaths(Vehicle $vehicle): array
    {
        $paths = [];

        if (is_string($vehicle->cover_photo_path) && $vehicle->cover_photo_path !== '') {
            $paths[] = $vehicle->cover_photo_path;
        }

        if (is_string($vehicle->cover_photo_portrait_path) && $vehicle->cover_photo_portrait_path !== '') {
            $paths[] = $vehicle->cover_photo_portrait_path;
        }

        foreach ($vehicle->maintenances as $maintenance) {
            $logoPath = $maintenance->workshop?->logo_path;
            if (is_string($logoPath) && $logoPath !== '') {
                $paths[] = $logoPath;
            }

            foreach ($maintenance->invoices ?? [] as $invoice) {
                $filePath = (string) $invoice->file_path;
                if ($filePath !== '') {
                    $paths[] = $filePath;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function revisalogBrandPath(string $filename): ?string
    {
        $path = public_path('images/brand/'.$filename);

        return is_file($path) ? $path : null;
    }

    private function revisalogLetterheadLogoSrc(): ?string
    {
        $path = public_path('images/brand/lockup-stacked-bar.png');
        if (! is_file($path)) {
            return $this->revisalogBrandPath('lockup-stacked-bar.png');
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $cropped = $this->brandBytesCroppedToLetterheadFrame($bytes);

        return $cropped !== null
            ? 'data:image/jpeg;base64,'.base64_encode($cropped)
            : $this->revisalogBrandPath('lockup-stacked-bar.png');
    }

    private function brandBytesCroppedToLetterheadFrame(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $outputWidth = self::WORKSHOP_LOGO_DISPLAY_WIDTH * self::WORKSHOP_LOGO_EMBED_SCALE;
        $outputHeight = self::WORKSHOP_LOGO_DISPLAY_HEIGHT * self::WORKSHOP_LOGO_EMBED_SCALE;
        $targetRatio = $outputWidth / $outputHeight;

        $windowY = (int) round($sourceHeight * 0.02);
        $windowHeight = (int) round($sourceHeight * 0.58);
        $cropX = 0;
        $cropWidth = $sourceWidth;
        $cropHeight = $windowHeight;
        $cropY = $windowY;

        $windowRatio = $cropWidth / $cropHeight;

        if ($windowRatio > $targetRatio) {
            $cropWidth = (int) round($cropHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        } else {
            $cropHeight = (int) round($cropWidth / $targetRatio);
        }

        if ($cropY + $cropHeight > $sourceHeight) {
            $cropHeight = $sourceHeight - $cropY;
        }

        $frame = imagecreatetruecolor($outputWidth, $outputHeight);

        if ($frame === false) {
            imagedestroy($source);

            return null;
        }

        try {
            imagecopyresampled(
                $frame,
                $source,
                0,
                0,
                $cropX,
                $cropY,
                $outputWidth,
                $outputHeight,
                $cropWidth,
                $cropHeight,
            );

            ob_start();
            imagejpeg($frame, null, self::LETTERHEAD_JPEG_QUALITY);
            $jpeg = ob_get_clean();

            return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
        } finally {
            imagedestroy($source);
            imagedestroy($frame);
        }
    }

    /**
     * @param  array{path: string, temporary: bool, content?: string}|null  $copy
     * @param  list<string>  $temps
     */
    private function imageSrcFromCopy(?array $copy, array &$temps): ?string
    {
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
     * @param  array{path: string, temporary: bool, content?: string}|null  $copy
     * @param  list<string>  $temps
     */
    private function workshopLogoSrcFromCopy(?array $copy, array &$temps): ?string
    {
        return $this->coverImageSrcFromCopy(
            $copy,
            $temps,
            self::WORKSHOP_LOGO_CROP_WIDTH,
            self::WORKSHOP_LOGO_CROP_HEIGHT,
            self::WORKSHOP_LOGO_EMBED_SCALE,
        );
    }

    /**
     * @param  array{path: string, temporary: bool, content?: string}|null  $copy
     * @param  list<string>  $temps
     */
    private function coverImageSrcFromCopy(
        ?array $copy,
        array &$temps,
        int $width = self::COVER_CROP_WIDTH,
        int $height = self::COVER_CROP_HEIGHT,
        int $embedScale = 1,
    ): ?string {
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

        $cropped = $this->coverBytesCroppedToFrame($bytes, $width, $height, $embedScale);

        return $cropped !== null
            ? 'data:image/jpeg;base64,'.base64_encode($cropped)
            : $this->imageSrcFromCopy($copy, $temps);
    }

    private function coverBytesCroppedToFrame(string $bytes, int $width, int $height, int $embedScale = 1): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $targetRatio = $width / $height;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        return $this->resampleCroppedRegionToJpeg(
            $source,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $width * $embedScale,
            $height * $embedScale,
        );
    }

    private function resampleCroppedRegionToJpeg(
        \GdImage $source,
        int $cropX,
        int $cropY,
        int $cropWidth,
        int $cropHeight,
        int $outputWidth,
        int $outputHeight,
    ): ?string {
        $frame = imagecreatetruecolor($outputWidth, $outputHeight);

        if ($frame === false) {
            imagedestroy($source);

            return null;
        }

        try {
            imagecopyresampled(
                $frame,
                $source,
                0,
                0,
                $cropX,
                $cropY,
                $outputWidth,
                $outputHeight,
                $cropWidth,
                $cropHeight,
            );

            ob_start();
            imagejpeg($frame, null, self::LETTERHEAD_JPEG_QUALITY);
            $jpeg = ob_get_clean();

            return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
        } finally {
            imagedestroy($source);
            imagedestroy($frame);
        }
    }

    /**
     * @param  array<string, array{path: string, temporary: bool, content?: string}>  $prefetched
     * @param  list<string>  $temps
     * @return list<array{invoice: Invoice, path: string, content?: string}>
     */
    private function invoiceCopiesFromPrefetched(Vehicle $vehicle, array $prefetched, array &$temps): array
    {
        $copies = [];

        foreach ($vehicle->maintenances as $maintenance) {
            foreach ($maintenance->invoices ?? [] as $invoice) {
                $filePath = (string) $invoice->file_path;
                if ($filePath === '') {
                    continue;
                }

                $copy = $prefetched[$filePath] ?? null;
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
