<?php

namespace App\Services\Invoice;

readonly class ParsedInvoiceItem
{
    public function __construct(
        public string $name,
        public float $quantity,
        public ?float $unitPrice,
        public ?float $totalPrice,
        public ?string $partNumber = null,
    ) {}
}
