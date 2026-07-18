<?php

namespace App\Services\Invoice;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InvoiceParser
{
    public function __construct(
        private readonly InvoicePdfParser $pdfParser = new InvoicePdfParser,
        private readonly NfeXmlParser $xmlParser = new NfeXmlParser,
    ) {}

    public function parseFile(string $path): ?ParsedInvoice
    {
        return $this->isXmlPath($path)
            ? $this->xmlParser->parseFile($path)
            : $this->pdfParser->parseFile($path);
    }

    public function parseUpload(UploadedFile $file): ?ParsedInvoice
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, ['xml'], true)
            ? $this->xmlParser->parseUpload($file)
            : $this->pdfParser->parseUpload($file);
    }

    public function parseStoredPath(string $storagePath): ?ParsedInvoice
    {
        return $this->isXmlPath($storagePath)
            ? $this->xmlParser->parseStoredPath($storagePath)
            : $this->pdfParser->parseStoredPath($storagePath);
    }

    private function isXmlPath(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xml';
    }
}
