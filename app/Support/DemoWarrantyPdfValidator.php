<?php

namespace App\Support;

use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Smalot\PdfParser\Parser;

class DemoWarrantyPdfValidator
{
    /**
     * @return list<string>
     */
    public static function validationErrors(string $pdfContent): array
    {
        $errors = [];

        if (! str_starts_with($pdfContent, '%PDF')) {
            $errors[] = 'PDF content does not start with %PDF.';

            return $errors;
        }

        $hasJpeg = str_contains($pdfContent, "\xFF\xD8\xFF");
        $hasPng = str_contains($pdfContent, "\x89PNG\r\n\x1A\n");

        if (! $hasJpeg && ! $hasPng) {
            $errors[] = 'PDF does not contain embedded JPEG or PNG logo bytes.';
        }

        $text = self::extractText($pdfContent);

        foreach ([
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA,
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA,
        ] as $anchor) {
            if (! str_contains($text, $anchor)) {
                $errors[] = "PDF text does not contain anchor: {$anchor}.";
            }
        }

        return $errors;
    }

    public static function isValid(string $pdfContent): bool
    {
        return self::validationErrors($pdfContent) === [];
    }

    public static function extractText(string $pdfContent): string
    {
        $parser = new Parser;
        $pdf = $parser->parseContent($pdfContent);

        return $pdf->getText();
    }

    public static function countPages(string $pdfContent): int
    {
        $count = preg_match_all('/\/Type[\s]*\/Page[^s]/', $pdfContent);

        return max(1, (int) $count);
    }

    public static function embeddedImageCount(string $pdfContent): int
    {
        return (int) preg_match_all('/\/Subtype[\s]*\/Image/', $pdfContent);
    }
}
