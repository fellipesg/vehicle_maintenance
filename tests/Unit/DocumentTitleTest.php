<?php

namespace Tests\Unit;

use App\Support\DocumentTitle;
use PHPUnit\Framework\TestCase;

class DocumentTitleTest extends TestCase
{
    public function test_public_page_title_is_page_and_brand(): void
    {
        $this->assertSame('Blog · RevisaLog', DocumentTitle::compose('Blog'));
    }

    public function test_portal_page_title_includes_the_area(): void
    {
        $this->assertSame('Manutenções · Proprietário · RevisaLog', DocumentTitle::compose('Manutenções', 'Proprietário'));
        $this->assertSame('Marcas · Admin · RevisaLog', DocumentTitle::compose('Marcas', 'Admin'));
    }

    public function test_legacy_brand_and_area_suffixes_are_removed(): void
    {
        $this->assertSame('Contato · RevisaLog', DocumentTitle::compose('Contato — RevisaLog'));
        $this->assertSame('Código inválido · RevisaLog', DocumentTitle::compose('Código inválido — RevisaLog — Histórico de Manutenções'));
        $this->assertSame('Veículos · Admin · RevisaLog', DocumentTitle::compose('Veículos — Admin', 'Admin'));
        $this->assertSame('Mapa de oficinas', DocumentTitle::pageName('Mapa de oficinas — Admin'));
    }

    public function test_brand_is_spelled_revisalog_with_capital_l(): void
    {
        $this->assertSame('RevisaLog', DocumentTitle::BRAND);
    }

    public function test_legacy_suffix_with_the_old_brand_spelling_is_removed(): void
    {
        $this->assertSame('Contato · RevisaLog', DocumentTitle::compose('Contato — Revisalog'));
        $this->assertSame('RevisaLog', DocumentTitle::compose('Revisalog'));
    }

    public function test_separators_inside_the_page_name_are_kept(): void
    {
        $this->assertSame('Consignação — procuração · Lojista · RevisaLog', DocumentTitle::compose('Consignação — procuração', 'Lojista'));
    }

    public function test_area_is_not_repeated_when_page_name_already_ends_with_it(): void
    {
        $this->assertSame('Painel Admin · RevisaLog', DocumentTitle::compose('Painel Admin', 'Admin'));
        $this->assertSame('Minha Oficina · RevisaLog', DocumentTitle::compose('Minha Oficina', 'Oficina'));
        $this->assertSame('Oficinas · Proprietário · RevisaLog', DocumentTitle::compose('Oficinas', 'Proprietário'));
    }

    public function test_empty_title_falls_back_to_area_and_brand(): void
    {
        $this->assertSame('RevisaLog', DocumentTitle::compose(''));
        $this->assertSame('RevisaLog', DocumentTitle::compose('RevisaLog'));
        $this->assertSame('Oficina · RevisaLog', DocumentTitle::compose('', 'Oficina'));
    }

    public function test_escaped_section_content_is_preserved(): void
    {
        $this->assertSame('Carro d&#039;água · RevisaLog', DocumentTitle::compose('Carro d&#039;água — RevisaLog'));
    }
}
