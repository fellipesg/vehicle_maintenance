<?php

namespace App\Services\Invoice;

class NfeDanfeParser
{
    public function canParse(string $text): bool
    {
        return str_contains($text, 'DANFE')
            || str_contains($text, 'DADOS DOS PRODUTOS')
            || str_contains($text, 'NOTA FISCAL ELETR');
    }

    public function parse(string $text): ?ParsedInvoice
    {
        if (! $this->canParse($text)) {
            return null;
        }

        $items = $this->extractItems($text);

        if ($items === []) {
            return null;
        }

        return new ParsedInvoice(
            type: 'nfe_danfe',
            items: $items,
            invoiceNumber: $this->extractInvoiceNumber($text),
            invoiceDate: $this->extractInvoiceDate($text),
            totalAmount: $this->extractTotalAmount($text),
            kilometers: $this->extractKilometers($text),
            licensePlate: $this->extractLicensePlate($text),
        );
    }

    /**
     * @return ParsedInvoiceItem[]
     */
    private function extractItems(string $text): array
    {
        $section = $this->productSection($text);

        if ($section === '') {
            return [];
        }

        $items = $this->extractDivesaStyleItems($section);

        if ($items !== []) {
            return $items;
        }

        return $this->extractFreeNfeStyleItems($section);
    }

    private function productSection(string $text): string
    {
        $start = strpos($text, 'DADOS DOS PRODUTOS');

        if ($start === false) {
            return '';
        }

        $endMarkers = [
            'ISS RETIDO',
            'RESERVADO AO FISCO',
            'INFORMAÇÕES COMPLEMENTARES',
            'INFORMACOES COMPLEMENTARES',
            'DADOS ADICIONAIS',
        ];

        $end = strlen($text);

        foreach ($endMarkers as $marker) {
            $position = strpos($text, $marker, $start);

            if ($position !== false && $position < $end) {
                $end = $position;
            }
        }

        return substr($text, $start, $end - $start);
    }

    /**
     * @return ParsedInvoiceItem[]
     */
    private function extractDivesaStyleItems(string $section): array
    {
        preg_match_all(
            '/([A-Z][A-Z0-9\s\.,\-\/\*]+?)\s+(UN|LT|PC|KG|CX|PAR|MT|M2|M3)\s+([\d.,]+)\s+([\d.,]+)(\d{8})/u',
            $section,
            $matches,
            PREG_SET_ORDER
        );

        $items = [];

        foreach ($matches as $index => $match) {
            $name = $this->cleanProductName($match[1]);
            if ($name === '') {
                continue;
            }

            $quantity = $this->parseBrazilianNumber($match[3]);
            $unitPrice = $this->parseBrazilianNumber($match[4]);
            $partNumber = $this->extractPartNumber($section, $index);

            $items[] = new ParsedInvoiceItem(
                name: $name,
                quantity: $quantity > 0 ? $quantity : 1,
                unitPrice: $unitPrice,
                totalPrice: round($quantity * $unitPrice, 2),
                partNumber: $partNumber,
            );
        }

        return $items;
    }

    /**
     * Layout comum em emissores Free NFe / lojas de autopeças.
     *
     * @return ParsedInvoiceItem[]
     */
    private function extractFreeNfeStyleItems(string $section): array
    {
        preg_match_all(
            '/^\s*(\d+)\s+(.+?)[\t\s]+(\d{8})\d+UN\s+(\d+)\s+(\d+,\d{2})/mu',
            $section,
            $matches,
            PREG_SET_ORDER
        );

        $items = [];

        foreach ($matches as $match) {
            $name = trim($match[2]);

            if ($name === '') {
                continue;
            }

            $quantity = (float) $match[4];
            $unitPrice = $this->parseBrazilianNumber($match[5]);
            $quantity = $quantity > 0 ? $quantity : 1;

            $items[] = new ParsedInvoiceItem(
                name: $name,
                quantity: $quantity,
                unitPrice: $unitPrice,
                totalPrice: round($quantity * $unitPrice, 2),
                partNumber: $match[1],
            );
        }

        return $items;
    }

    private function cleanProductName(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/(\d{3})([A-Z].+)$/u', $raw, $match)) {
            return trim($match[2]);
        }

        return preg_replace('/^[\d,\.\s]+/u', '', $raw) ?? $raw;
    }

    private function extractPartNumber(string $section, int $itemIndex): ?string
    {
        preg_match_all('/(?:TPC|IPC)([A-Z0-9\.\s]+)/u', $section, $partNumbers);

        $partNumber = trim($partNumbers[1][$itemIndex] ?? '');

        return $partNumber !== '' ? $partNumber : null;
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match('/Nr\.:\s*(\d+)/u', $text, $match)) {
            return ltrim($match[1], '0') ?: $match[1];
        }

        if (preg_match('/Nº:\s*(\d+)/u', $text, $match)) {
            return ltrim($match[1], '0') ?: $match[1];
        }

        return null;
    }

    private function extractInvoiceDate(string $text): ?string
    {
        if (preg_match('/DATA DA EMISSAO[\s\S]*?(\d{2}\/\d{2}\/\d{4})/u', $text, $match)) {
            return $this->brDateToIso($match[1]);
        }

        if (preg_match('/EMISSÃO:\s*(\d{2}\/\d{2}\/\d{4})/u', $text, $match)) {
            return $this->brDateToIso($match[1]);
        }

        return null;
    }

    private function extractTotalAmount(string $text): ?float
    {
        if (preg_match('/VISA\s*\/\s*MASTER\s*R\$([\d.,]+)/u', $text, $match)) {
            return $this->parseBrazilianNumber($match[1]);
        }

        if (preg_match('/VALOR TOTAL:\s*R\$\s*([\d.,]+)/u', $text, $match)) {
            return $this->parseBrazilianNumber($match[1]);
        }

        if (preg_match('/0,00\s+0,00\s+0,00\s+0,00\s+([\d.,]+)/u', $text, $match)) {
            return $this->parseBrazilianNumber($match[1]);
        }

        return null;
    }

    private function extractKilometers(string $text): ?int
    {
        if (preg_match('/KM:\s*(\d+)/u', $text, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function extractLicensePlate(string $text): ?string
    {
        if (preg_match('/Placa:\s*([A-Z0-9]{7})/u', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    private function brDateToIso(string $date): string
    {
        [$day, $month, $year] = explode('/', $date);

        return sprintf('%s-%s-%s', $year, $month, $day);
    }

    private function parseBrazilianNumber(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            return (float) str_replace(',', '.', str_replace('.', '', $value));
        }

        if (str_contains($value, ',')) {
            return (float) str_replace(',', '.', $value);
        }

        return (float) $value;
    }
}
