<?php

namespace App\Services\Invoice;

readonly class ParsedInvoice
{
    /**
     * @param  ParsedInvoiceItem[]  $items
     */
    public function __construct(
        public string $type,
        public array $items,
        public ?string $invoiceNumber = null,
        public ?string $invoiceDate = null,
        public ?float $totalAmount = null,
        public ?int $kilometers = null,
        public ?string $licensePlate = null,
    ) {}
}
