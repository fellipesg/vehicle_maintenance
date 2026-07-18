<?php

namespace App\Services\Crlv;

use App\Services\VehicleCatalogService;

class CrlvBrandModelResolver
{
    /** @var array<string, string> */
    private array $brandAliases;

    /** @var string[] */
    private const MODEL_SUFFIXES = [
        '4X4', '4X2', 'FLEX', 'FF', 'TURBO', 'AUT', 'MEC', 'CVT', 'AT', 'MT',
        'LXR', 'LX', 'EX', 'EXL', 'DX', 'DXL', 'DXL.', 'SE', 'SEL', 'SPORT',
        'PREMIUM', 'LIMITED', 'EXECUTIVE', 'CDI', 'TDI', 'HDI', 'TSI', 'TFSI',
        '16V', '8V', 'AWD', '4WD', '2WD',
    ];

    public function __construct(
        private readonly VehicleCatalogService $catalog,
    ) {
        $this->brandAliases = require database_path('data/crlv_brand_aliases.php');
    }

    /**
     * @return array{brand: string, model: string, brand_matched: bool, model_matched: bool}
     */
    public function resolve(string $brandModelLine): array
    {
        [$brandRaw, $modelRaw] = $this->splitBrandModel($brandModelLine);
        $catalog = $this->catalog->all();

        $brand = $this->resolveBrand($brandRaw, $catalog);
        $brandMatched = isset($catalog[$brand]);

        $model = $this->resolveModel($modelRaw, $brand, $catalog);
        $modelMatched = $brandMatched && in_array($model, $catalog[$brand] ?? [], true);

        return [
            'brand' => $brand,
            'model' => $model,
            'brand_matched' => $brandMatched,
            'model_matched' => $modelMatched,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitBrandModel(string $line): array
    {
        $line = trim($line);

        if (str_contains($line, '/')) {
            [$brand, $model] = explode('/', $line, 2);

            return [trim($brand), trim($model)];
        }

        return ['', $line];
    }

    /**
     * @param  array<string, string[]>  $catalog
     */
    private function resolveBrand(string $brandRaw, array $catalog): string
    {
        $normalized = $this->normalizeToken($brandRaw);

        if ($normalized === '') {
            return $brandRaw;
        }

        if (isset($this->brandAliases[$normalized])) {
            return $this->brandAliases[$normalized];
        }

        foreach ($catalog as $catalogBrand => $_) {
            if ($this->normalizeToken($catalogBrand) === $normalized) {
                return $catalogBrand;
            }
        }

        foreach ($catalog as $catalogBrand => $_) {
            $catalogNorm = $this->normalizeToken($catalogBrand);
            if (str_starts_with($catalogNorm, $normalized) || str_starts_with($normalized, $catalogNorm)) {
                return $catalogBrand;
            }
        }

        return $this->prettifyBrand($brandRaw);
    }

    /**
     * @param  array<string, string[]>  $catalog
     */
    private function resolveModel(string $modelRaw, string $brand, array $catalog): string
    {
        $cleaned = $this->cleanModelRaw($modelRaw);
        $models = $catalog[$brand] ?? [];

        if ($models === []) {
            return $this->prettifyModel($cleaned);
        }

        $candidates = $this->modelCandidates($cleaned);

        foreach ($candidates as $candidate) {
            foreach ($models as $catalogModel) {
                if (strcasecmp($candidate, $catalogModel) === 0) {
                    return $catalogModel;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidateNorm = $this->compact($candidate);
            foreach ($models as $catalogModel) {
                if ($this->compact($catalogModel) === $candidateNorm) {
                    return $catalogModel;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidateNorm = $this->compact($candidate);
            foreach ($models as $catalogModel) {
                $catalogNorm = $this->compact($catalogModel);
                if (str_starts_with($candidateNorm, $catalogNorm) || str_starts_with($catalogNorm, $candidateNorm)) {
                    return $catalogModel;
                }
            }
        }

        $bestModel = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            foreach ($models as $catalogModel) {
                similar_text($this->compact($candidate), $this->compact($catalogModel), $score);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestModel = $catalogModel;
                }
            }
        }

        if ($bestModel !== null && $bestScore >= 70) {
            return $bestModel;
        }

        return $this->prettifyModel($cleaned);
    }

    private function cleanModelRaw(string $modelRaw): string
    {
        $model = trim($modelRaw);
        $tokens = preg_split('/\s+/', $model) ?: [];
        $filtered = [];

        foreach ($tokens as $token) {
            $normalized = $this->normalizeToken($token);
            if ($normalized === '' || in_array($normalized, self::MODEL_SUFFIXES, true)) {
                continue;
            }
            $filtered[] = $token;
        }

        return trim(implode(' ', $filtered));
    }

    /**
     * @return string[]
     */
    private function modelCandidates(string $cleaned): array
    {
        $candidates = [$cleaned];

        if (preg_match('/^([A-Z])\s*(\d{2,4})/i', $cleaned, $matches)) {
            $candidates[] = strtoupper($matches[1]).' '.$matches[2];
            $candidates[] = strtoupper($matches[1]).$matches[2];
        }

        if (preg_match('/^CIVIC/i', $cleaned)) {
            $candidates[] = 'Civic';
        }

        if (preg_match('/^COROLLA/i', $cleaned)) {
            $candidates[] = 'Corolla';
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeToken(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = str_replace(['.', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function compact(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/i', '', mb_strtoupper($value)) ?? '';
    }

    private function prettifyBrand(string $brandRaw): string
    {
        $brand = trim(str_replace('.', ' ', $brandRaw));

        return mb_convert_case($brand, MB_CASE_TITLE, 'UTF-8');
    }

    private function prettifyModel(string $modelRaw): string
    {
        if (preg_match('/^([A-Z])(\d{2,4})/i', $modelRaw, $matches)) {
            return strtoupper($matches[1]).' '.$matches[2];
        }

        return mb_convert_case($modelRaw, MB_CASE_TITLE, 'UTF-8');
    }
}
