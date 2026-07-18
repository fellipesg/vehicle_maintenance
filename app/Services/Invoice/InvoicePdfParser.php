<?php

namespace App\Services\Invoice;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class InvoicePdfParser
{
    private Parser $pdfParser;

    public function __construct(
        private readonly NfeDanfeParser $nfeParser = new NfeDanfeParser,
        ?Parser $pdfParser = null,
    ) {
        $this->pdfParser = $pdfParser ?? new Parser;
    }

    public function parseFile(string $path): ?ParsedInvoice
    {
        try {
            if (! is_readable($path) || filesize($path) === 0) {
                return null;
            }

            $text = $this->pdfParser->parseFile($path)->getText();

            return $this->parseText($text);
        } catch (Throwable) {
            return null;
        }
    }

    public function parseUpload(UploadedFile $file): ?ParsedInvoice
    {
        $path = $file->getRealPath();

        return $path ? $this->parseFile($path) : null;
    }

    public function parseStoredPath(string $storagePath): ?ParsedInvoice
    {
        return $this->parseFile(Storage::disk('public')->path($storagePath));
    }

    public function parseText(string $text): ?ParsedInvoice
    {
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;

        return $this->nfeParser->parse($text);
    }
}
