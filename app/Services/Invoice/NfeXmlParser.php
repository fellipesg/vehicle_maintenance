<?php

namespace App\Services\Invoice;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class NfeXmlParser
{
    public function parseFile(string $path): ?ParsedInvoice
    {
        if (! is_readable($path) || filesize($path) === 0) {
            return null;
        }

        $content = file_get_contents($path);

        return $content ? $this->parseString($content) : null;
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

    public function parseString(string $xml): ?ParsedInvoice
    {
        try {
            $document = $this->loadXml($xml);

            if ($document === null) {
                return null;
            }

            $items = $this->extractItems($document);

            if ($items === []) {
                return null;
            }

            return new ParsedInvoice(
                type: 'nfe_xml',
                items: $items,
                invoiceNumber: $this->extractInvoiceNumber($document),
                invoiceDate: $this->extractInvoiceDate($document),
                totalAmount: $this->extractTotalAmount($document),
                kilometers: $this->extractKilometers($document),
                licensePlate: $this->extractLicensePlate($document),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function loadXml(string $xml): ?\SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $document = simplexml_load_string($xml);

        if ($document === false) {
            return null;
        }

        return $document;
    }

    /**
     * @return ParsedInvoiceItem[]
     */
    private function extractItems(\SimpleXMLElement $document): array
    {
        $items = [];

        foreach ($document->xpath('//*[local-name()="det"]') ?: [] as $det) {
            $productNodes = $det->xpath('.//*[local-name()="prod"]');

            if ($productNodes === false || $productNodes === []) {
                continue;
            }

            /** @var \SimpleXMLElement $product */
            $product = $productNodes[0];
            $name = trim((string) ($product->xpath('.//*[local-name()="xProd"]')[0] ?? ''));

            if ($name === '') {
                continue;
            }

            $quantity = (float) ($product->xpath('.//*[local-name()="qCom"]')[0] ?? 1);
            $unitPrice = $this->toFloat($product->xpath('.//*[local-name()="vUnCom"]')[0] ?? null);
            $totalPrice = $this->toFloat($product->xpath('.//*[local-name()="vProd"]')[0] ?? null);
            $partNumber = trim((string) ($product->xpath('.//*[local-name()="cProd"]')[0] ?? '')) ?: null;

            if ($totalPrice === null && $unitPrice !== null) {
                $totalPrice = round(max($quantity, 1) * $unitPrice, 2);
            }

            $items[] = new ParsedInvoiceItem(
                name: $name,
                quantity: $quantity > 0 ? $quantity : 1,
                unitPrice: $unitPrice,
                totalPrice: $totalPrice,
                partNumber: $partNumber,
            );
        }

        return $items;
    }

    private function extractInvoiceNumber(\SimpleXMLElement $document): ?string
    {
        $number = trim((string) ($document->xpath('//*[local-name()="ide"]/*[local-name()="nNF"]')[0] ?? ''));

        if ($number === '') {
            return null;
        }

        return ltrim($number, '0') ?: $number;
    }

    private function extractInvoiceDate(\SimpleXMLElement $document): ?string
    {
        $emittedAt = trim((string) ($document->xpath('//*[local-name()="ide"]/*[local-name()="dhEmi"]')[0] ?? ''));

        if ($emittedAt === '') {
            $emittedAt = trim((string) ($document->xpath('//*[local-name()="ide"]/*[local-name()="dEmi"]')[0] ?? ''));
        }

        if ($emittedAt === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $emittedAt, $match)) {
            return $match[1];
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $emittedAt, $match)) {
            return sprintf('%s-%s-%s', $match[3], $match[2], $match[1]);
        }

        return null;
    }

    private function extractTotalAmount(\SimpleXMLElement $document): ?float
    {
        return $this->toFloat($document->xpath('//*[local-name()="ICMSTot"]/*[local-name()="vNF"]')[0] ?? null);
    }

    private function extractKilometers(\SimpleXMLElement $document): ?int
    {
        foreach ($document->xpath('//*[local-name()="infAdProd"] | //*[local-name()="infCpl"]') ?: [] as $node) {
            $text = (string) $node;

            if (preg_match('/KM:\s*(\d+)/iu', $text, $match)) {
                return (int) $match[1];
            }
        }

        return null;
    }

    private function extractLicensePlate(\SimpleXMLElement $document): ?string
    {
        foreach ($document->xpath('//*[local-name()="infAdProd"] | //*[local-name()="infCpl"]') ?: [] as $node) {
            $text = (string) $node;

            if (preg_match('/Placa:\s*([A-Z0-9]{7})/iu', $text, $match)) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return (float) $normalized;
    }
}
