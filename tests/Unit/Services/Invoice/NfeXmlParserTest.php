<?php

namespace Tests\Unit\Services\Invoice;

use App\Services\Invoice\InvoiceParser;
use App\Services\Invoice\NfeXmlParser;
use Tests\TestCase;

class NfeXmlParserTest extends TestCase
{
    public function test_parses_nfe_xml_items_from_fixture(): void
    {
        $parsed = (new InvoiceParser)->parseFile(
            base_path('tests/fixtures/invoices/troia_nfe_4250.xml')
        );

        $this->assertNotNull($parsed);
        $this->assertSame('nfe_xml', $parsed->type);
        $this->assertSame('4250', $parsed->invoiceNumber);
        $this->assertSame('2023-07-12', $parsed->invoiceDate);
        $this->assertSame(240.68, $parsed->totalAmount);
        $this->assertCount(5, $parsed->items);

        $first = $parsed->items[0];
        $this->assertSame('Óleo Lubrificante 0w20 Idemitsu', $first->name);
        $this->assertSame(4.0, $first->quantity);
        $this->assertSame(65.67, $first->unitPrice);
        $this->assertSame('2059', $first->partNumber);
    }

    public function test_returns_null_for_invalid_xml(): void
    {
        $parsed = (new NfeXmlParser)->parseString('<nota><sem-itens/></nota>');

        $this->assertNull($parsed);
    }
}
