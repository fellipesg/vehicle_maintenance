<?php

namespace Tests\Unit\Services\Invoice;

use App\Services\Invoice\InvoicePdfParser;
use App\Services\Invoice\NfeDanfeParser;
use Tests\TestCase;

class NfeDanfeParserTest extends TestCase
{
    public function test_parses_divesa_nfe_items_from_fixture_pdf(): void
    {
        $parsed = (new InvoicePdfParser)->parseFile(
            base_path('tests/fixtures/invoices/divesa_nfe_80000.pdf')
        );

        $this->assertNotNull($parsed);
        $this->assertSame('nfe_danfe', $parsed->type);
        $this->assertSame('31715', $parsed->invoiceNumber);
        $this->assertSame('2025-01-09', $parsed->invoiceDate);
        $this->assertSame(4417.20, $parsed->totalAmount);
        $this->assertSame(80000, $parsed->kilometers);
        $this->assertSame('QOS6H54', $parsed->licensePlate);
        $this->assertCount(10, $parsed->items);

        $first = $parsed->items[0];
        $this->assertSame('FILTRO DE POEIRA', $first->name);
        $this->assertSame(1.0, $first->quantity);
        $this->assertSame(240.97, $first->unitPrice);
    }

    public function test_returns_null_for_non_nfe_text(): void
    {
        $parsed = (new NfeDanfeParser)->parse('Documento qualquer sem layout de nota fiscal');

        $this->assertNull($parsed);
    }

    public function test_parses_free_nfe_style_items_from_fixture_pdf(): void
    {
        $parsed = (new InvoicePdfParser)->parseFile(
            base_path('tests/fixtures/invoices/troia_nfe_4250.pdf')
        );

        $this->assertNotNull($parsed);
        $this->assertSame('nfe_danfe', $parsed->type);
        $this->assertCount(5, $parsed->items);

        $first = $parsed->items[0];
        $this->assertStringContainsString('Óleo Lubrificante', $first->name);
        $this->assertSame(4.0, $first->quantity);
        $this->assertSame(65.67, $first->unitPrice);
        $this->assertSame('2059', $first->partNumber);
    }
}
