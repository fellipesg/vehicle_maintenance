<?php

namespace App\Services\Crlv;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;

class CrlvPdfParser
{
    private const REQUIRED_MARKERS = [
        'SENATRAN',
        'CERTIFICADO DE REGISTRO E LICENCIAMENTO DE VEÍCULO - DIGITAL',
        'CÓDIGO RENAVAM',
    ];

    public function __construct(
        private readonly Parser $pdfParser,
        private readonly CrlvBrandModelResolver $brandModelResolver,
    ) {}

    public function parseFile(string $path): CrlvParseResult
    {
        $text = $this->pdfParser->parseFile($path)->getText();

        return $this->parseText($text);
    }

    public function parseUpload(UploadedFile $file): CrlvParseResult
    {
        return $this->parseFile($file->getRealPath() ?: $file->path());
    }

    public function isCrlvDocument(string $path): bool
    {
        try {
            $text = $this->pdfParser->parseFile($path)->getText();
        } catch (\Throwable) {
            return false;
        }

        return $this->looksLikeCrlv($text) && $this->extractDataBlock($text) !== null;
    }

    public function parseText(string $text): CrlvParseResult
    {
        if (! $this->looksLikeCrlv($text)) {
            throw new RuntimeException('O PDF não parece ser um CRLV-e digital da SENATRAN.');
        }

        $block = $this->extractDataBlock($text);

        if ($block === null) {
            throw new RuntimeException('Não foi possível localizar os dados do veículo no CRLV-e.');
        }

        return $this->parseDataBlock($block, $text);
    }

    private function looksLikeCrlv(string $text): bool
    {
        foreach (self::REQUIRED_MARKERS as $marker) {
            if (! str_contains($text, $marker)) {
                return false;
            }
        }

        return true;
    }

    private function extractDataBlock(string $text): ?string
    {
        if (preg_match('/Leia o QR Code e baixe agora\.\s*(.+?)\s*Documento emitido por CDT/su', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(\d{11}\s+[A-Z0-9]{7}\s+\d{4}\s+\d{4}.+)$/su', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function parseDataBlock(string $block, string $fullText): CrlvParseResult
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $block) ?: [])));

        if (count($lines) < 10) {
            throw new RuntimeException('O CRLV-e não contém linhas suficientes para importação.');
        }

        if (! preg_match('/^(\d{9,11})$/', $lines[0], $renavamMatch)) {
            throw new RuntimeException('RENAVAM não encontrado no CRLV-e.');
        }

        if (! preg_match('/^([A-Z0-9]{7})\s+(\d{4})$/', $lines[1], $plateMatch)) {
            throw new RuntimeException('Placa não encontrada no CRLV-e.');
        }

        $exerciseYear = (int) $plateMatch[2];

        if (! preg_match('/^(\d{4})\s+(\d{4})$/', $lines[2], $yearMatch)) {
            throw new RuntimeException('Ano do veículo não encontrado no CRLV-e.');
        }

        $manufacturingYear = (int) $yearMatch[1];
        $crvNumber = $this->findCrvNumber($lines);
        $owner = $this->extractOwner($lines);

        $brandModelLine = $this->findBrandModelLine($lines);

        if ($brandModelLine === null) {
            throw new RuntimeException('Marca e modelo não encontrados no CRLV-e.');
        }

        $resolved = $this->brandModelResolver->resolve($brandModelLine);
        $brandModelIndex = array_search($brandModelLine, $lines, true);

        if ($brandModelIndex === false || ! isset($lines[$brandModelIndex + 3])) {
            throw new RuntimeException('Estrutura do CRLV-e não reconhecida.');
        }

        $plateChassisLine = $lines[$brandModelIndex + 2];
        $colorFuelLine = $lines[$brandModelIndex + 3];
        $powerLine = null;
        $motorLine = null;

        for ($i = $brandModelIndex + 4; $i < count($lines); $i++) {
            if (preg_match('/\d+CV/i', $lines[$i])) {
                $powerLine = $lines[$i];
                $motorLine = $lines[$i + 1] ?? null;
                break;
            }
        }
        $detranState = $this->extractDetranState($block, $fullText);

        [$color, $fuel] = $this->parseColorFuel($colorFuelLine);
        $chassis = $this->extractChassis($plateChassisLine);
        $motorization = $this->extractMotorization($powerLine);

        return new CrlvParseResult(
            licensePlate: $plateMatch[1],
            renavam: $renavamMatch[1],
            brand: $resolved['brand'],
            model: $resolved['model'],
            year: (int) $yearMatch[2],
            color: $color,
            chassis: $chassis,
            engine: $this->extractEngine($motorLine),
            motorization: $motorization,
            brandRaw: explode('/', $brandModelLine, 2)[0] ?? $brandModelLine,
            modelRaw: explode('/', $brandModelLine, 2)[1] ?? '',
            brandMatched: $resolved['brand_matched'],
            modelMatched: $resolved['model_matched'],
            detranState: $detranState,
            fuel: $fuel,
            crvNumber: $crvNumber,
            exerciseYear: $exerciseYear,
            manufacturingYear: $manufacturingYear,
            ownerName: $owner['name'],
            ownerDocument: $owner['document'],
        );
    }

    private function extractCrvNumber(string $line): ?string
    {
        if (preg_match('/^(\d{9,15})$/', trim($line), $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{9,15})\b/', trim($line), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  string[]  $lines
     */
    private function findCrvNumber(array $lines): ?string
    {
        // Layout CDT: RENAVAM, placa+exercício, anos, número do CRV, código CLA, marca/modelo...
        foreach ([3, 4] as $index) {
            $candidate = $this->extractCrvNumber($lines[$index] ?? '');
            if ($candidate !== null) {
                return $candidate;
            }
        }

        foreach ($lines as $index => $line) {
            if ($index < 3) {
                continue;
            }

            if (preg_match('/^[A-Z0-9.\s-]+\/[A-Z0-9.\s-]+$/i', $line)) {
                break;
            }

            $candidate = $this->extractCrvNumber($line);
            if ($candidate !== null && strlen($candidate) >= 10) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  string[]  $lines
     * @return array{name: ?string, document: ?string}
     */
    private function extractOwner(array $lines): array
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/^(\d{3}\.\d{3}\.\d{3}-\d{2}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/', $line)) {
                return [
                    'name' => $lines[$index - 1] ?? null,
                    'document' => $line,
                ];
            }
        }

        return ['name' => null, 'document' => null];
    }

    /**
     * @param  string[]  $lines
     */
    private function findBrandModelLine(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^[A-Z0-9.\s-]+\/[A-Z0-9.\s-]+$/i', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseColorFuel(?string $line): array
    {
        if ($line === null) {
            return [null, null];
        }

        $parts = preg_split('/\s+/', trim($line)) ?: [];

        if (count($parts) >= 2) {
            $fuel = array_pop($parts);
            $color = implode(' ', $parts);

            return [$color, $fuel];
        }

        return [$line, null];
    }

    private function extractChassis(?string $line): ?string
    {
        if ($line === null) {
            return null;
        }

        if (preg_match('/([A-HJ-NPR-Z0-9]{17})/', $line, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractMotorization(?string $line): ?string
    {
        if ($line === null) {
            return null;
        }

        $parts = [];

        if (preg_match('/(\d+)CV/i', $line, $cvMatch)) {
            $parts[] = $cvMatch[1].'CV';
        }

        if (preg_match('/\/(\d{3,4})/', $line, $ccMatch)) {
            $liters = round(((int) $ccMatch[1]) / 1000, 1);
            $parts[] = $liters.'L';
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function extractEngine(?string $line): ?string
    {
        if ($line === null) {
            return null;
        }

        $engine = trim(preg_split('/\s+/', $line)[0] ?? $line);

        return $engine !== '' && $engine !== '*' ? $engine : null;
    }

    private function extractDetranState(string $block, string $fullText): ?string
    {
        if (preg_match('/\b([A-Z]{2})\s*\*?\s*Documento emitido por CDT/su', $fullText, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b([A-Z]{2})\s*$/m', $block, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
