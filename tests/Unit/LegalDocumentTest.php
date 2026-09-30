<?php

namespace Tests\Unit;

use App\Support\LegalDocument;
use PHPUnit\Framework\TestCase;

class LegalDocumentTest extends TestCase
{
    public function test_it_drops_the_title_line_and_splits_sections_paragraphs_and_lists(): void
    {
        $document = LegalDocument::fromText(<<<'TEXT'
Política de Privacidade — RevisaLog

Esta política descreve como tratamos dados.

1. Dados que coletamos
- Conta: nome e e-mail.
- Endereço: CEP.

2. Seus direitos
Você pode pedir acesso.
TEXT);

        $this->assertSame([
            ['type' => 'paragraph', 'text' => 'Esta política descreve como tratamos dados.'],
            ['type' => 'heading', 'text' => '1. Dados que coletamos', 'id' => 'dados-que-coletamos'],
            ['type' => 'list', 'items' => ['Conta: nome e e-mail.', 'Endereço: CEP.']],
            ['type' => 'heading', 'text' => '2. Seus direitos', 'id' => 'seus-direitos'],
            ['type' => 'paragraph', 'text' => 'Você pode pedir acesso.'],
        ], $document->blocks);
    }

    public function test_sections_list_the_headings_in_order_for_the_table_of_contents(): void
    {
        $document = LegalDocument::fromText("Termos\n\n1. Aceite\nTexto.\n\n2. Contato\nTexto.");

        $this->assertSame([
            ['id' => 'aceite', 'text' => '1. Aceite'],
            ['id' => 'contato', 'text' => '2. Contato'],
        ], $document->sections());
    }

    public function test_section_ids_ignore_the_number_so_renumbering_keeps_links(): void
    {
        $before = LegalDocument::fromText("Termos\n\n7. Seus direitos\nTexto.");
        $after = LegalDocument::fromText("Termos\n\n8. Seus direitos\nTexto.");

        $this->assertSame('seus-direitos', $before->sections()[0]['id']);
        $this->assertSame($before->sections()[0]['id'], $after->sections()[0]['id']);
    }

    public function test_repeated_titles_get_unique_ids(): void
    {
        $document = LegalDocument::fromText("Termos\n\n1. Contato\nA.\n\n2. Contato\nB.\n\n3. Contato\nC.");

        $this->assertSame(['contato', 'contato-2', 'contato-3'], array_column($document->sections(), 'id'));
    }

    public function test_title_without_letters_falls_back_to_the_section_number(): void
    {
        $document = LegalDocument::fromText("Termos\n\n4. —\nTexto.");

        $this->assertSame('secao-4', $document->sections()[0]['id']);
    }

    public function test_introduction_is_the_paragraph_before_the_first_section(): void
    {
        $this->assertSame('Abertura.', LegalDocument::fromText("Termos\n\nAbertura.\n\n1. Aceite\nTexto.")->introduction());
        $this->assertNull(LegalDocument::fromText("Termos\n\n1. Aceite\nTexto.")->introduction());
        $this->assertNull(LegalDocument::fromText(null)->introduction());
        $this->assertSame([], LegalDocument::fromText('')->blocks);
    }

    public function test_numbers_inside_a_paragraph_do_not_open_a_section(): void
    {
        $document = LegalDocument::fromText("Termos\n\nRespondemos em até 15 dias.\n2025 foi o ano de lançamento.");

        $this->assertSame([], $document->sections());
        $this->assertCount(2, $document->blocks);
    }
}
