<?php

namespace App\Services\Crlv;

class CrlvParseResult
{
    public function __construct(
        public readonly string $licensePlate,
        public readonly string $renavam,
        public readonly string $brand,
        public readonly string $model,
        public readonly int $year,
        public readonly ?string $color = null,
        public readonly ?string $chassis = null,
        public readonly ?string $engine = null,
        public readonly ?string $motorization = null,
        public readonly string $brandRaw = '',
        public readonly string $modelRaw = '',
        public readonly bool $brandMatched = false,
        public readonly bool $modelMatched = false,
        public readonly ?string $detranState = null,
        public readonly ?string $fuel = null,
        public readonly ?string $crvNumber = null,
        public readonly ?int $exerciseYear = null,
        public readonly ?int $manufacturingYear = null,
        public readonly ?string $ownerName = null,
        public readonly ?string $ownerDocument = null,
    ) {}

    /**
     * Reconstrói o resultado a partir do que foi guardado entre requisições.
     *
     * @param  array<string, mixed>  $preview
     */
    public static function fromPreview(array $preview): self
    {
        return new self(
            licensePlate: (string) $preview['license_plate'],
            renavam: (string) $preview['renavam'],
            brand: (string) $preview['brand'],
            model: (string) $preview['model'],
            year: (int) $preview['year'],
            color: $preview['color'] ?? null,
            chassis: $preview['chassis'] ?? null,
            engine: $preview['engine'] ?? null,
            motorization: $preview['motorization'] ?? null,
            brandRaw: (string) ($preview['brand_raw'] ?? ''),
            modelRaw: (string) ($preview['model_raw'] ?? ''),
            brandMatched: (bool) ($preview['brand_matched'] ?? false),
            modelMatched: (bool) ($preview['model_matched'] ?? false),
            detranState: $preview['detran_state'] ?? null,
            fuel: $preview['fuel'] ?? null,
            crvNumber: $preview['crv_number'] ?? null,
            exerciseYear: isset($preview['exercise_year']) ? (int) $preview['exercise_year'] : null,
            manufacturingYear: isset($preview['manufacturing_year']) ? (int) $preview['manufacturing_year'] : null,
            ownerName: $preview['owner_name'] ?? null,
            ownerDocument: $preview['owner_document'] ?? null,
        );
    }

    public function normalizedOwnerDocument(): ?string
    {
        if ($this->ownerDocument === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->ownerDocument) ?: null;
    }

    public function normalizedRenavam(): string
    {
        return preg_replace('/\D/', '', $this->renavam) ?: $this->renavam;
    }

    public function normalizedCrvNumber(): ?string
    {
        if ($this->crvNumber === null) {
            return null;
        }

        return preg_replace('/\D/', '', $this->crvNumber) ?: null;
    }

    public function verificationToken(): string
    {
        return hash('sha256', implode('|', [
            $this->normalizedRenavam(),
            $this->normalizedCrvNumber() ?? '',
            (string) ($this->exerciseYear ?? ''),
            $this->licensePlate,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormData(): array
    {
        return [
            'license_plate' => $this->licensePlate,
            'renavam' => $this->renavam,
            'crv_number' => $this->crvNumber,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'chassis' => $this->chassis,
            'engine' => $this->engine,
            'motorization' => $this->motorization,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPreview(): array
    {
        return [
            ...$this->toFormData(),
            'brand_raw' => $this->brandRaw,
            'model_raw' => $this->modelRaw,
            'brand_matched' => $this->brandMatched,
            'model_matched' => $this->modelMatched,
            'detran_state' => $this->detranState,
            'fuel' => $this->fuel,
            'exercise_year' => $this->exerciseYear,
            'manufacturing_year' => $this->manufacturingYear,
            'owner_name' => $this->ownerName,
            'owner_document' => $this->ownerDocument,
            'crlv_verification_token' => $this->verificationToken(),
        ];
    }
}
