<?php

namespace Tests\Unit\Rules;

use App\Rules\InvoiceFile;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvoiceFileTest extends TestCase
{
    public function test_accepts_the_nfe_pdf_fixture(): void
    {
        $this->assertSame([], $this->failures($this->fixture('divesa_nfe_80000.pdf', 'nota.pdf')));
    }

    public function test_accepts_the_nfe_xml_fixture_declared_as_xml(): void
    {
        $this->assertSame([], $this->failures($this->fixture('troia_nfe_4250.xml', 'nota.xml')));
    }

    /**
     * divesa_nfe_33893.xml não traz a declaração `<?xml ?>`, então o fileinfo devolve
     * text/plain. Uma validação só por MIME recusaria uma NF-e legítima.
     */
    public function test_accepts_the_nfe_xml_fixture_detected_as_text_plain(): void
    {
        $file = $this->fixture('divesa_nfe_33893.xml', 'Nota_Fiscal_33893-3.xml');

        $this->assertSame('text/plain', $file->getMimeType());
        $this->assertSame([], $this->failures($file));
    }

    /**
     * NF-e sem a declaração de XML e gravada em ISO-8859-1: o fileinfo devolve text/plain
     * e o libxml, sem declaração, assume UTF-8 e tromba no acento. Recusar isso barraria
     * uma nota legítima, e a OS que exige nota fiscal nem poderia ser salva.
     */
    public function test_accepts_a_declaration_less_nfe_xml_in_iso_8859_1(): void
    {
        $latin1 = "<nfeProc><NFe><infNFe><xProd>LUBRIFICA\xC7\xC3O</xProd></infNFe></NFe></nfeProc>";

        $file = $this->upload($latin1, 'nota.xml');

        $this->assertSame('text/plain', $file->getMimeType());
        $this->assertFalse(mb_check_encoding($latin1, 'UTF-8'));
        $this->assertSame([], $this->failures($file));
    }

    public function test_accepts_an_nfe_xml_that_declares_iso_8859_1(): void
    {
        $declared = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?><nfeProc><xProd>A\xC7\xC3O</xProd></nfeProc>";

        $this->assertSame([], $this->failures($this->upload($declared, 'nota.xml')));
    }

    public function test_accepts_an_nfe_xml_with_a_utf8_bom(): void
    {
        $withBom = "\xEF\xBB\xBF<?xml version=\"1.0\"?><nfeProc><xProd>FILTRO</xProd></nfeProc>";

        $this->assertSame([], $this->failures($this->upload($withBom, 'nota.xml')));
    }

    public function test_rejects_a_shell_script_renamed_to_pdf(): void
    {
        $failures = $this->failures($this->upload("#!/bin/sh\necho pwned\n", 'nota.pdf'));

        $this->assertSame(['O arquivo enviado não é um PDF. Envie o PDF ou o XML da nota fiscal.'], $failures);
    }

    public function test_rejects_an_executable_renamed_to_pdf(): void
    {
        $executable = "\x7fELF\x02\x01\x01\x00".str_repeat("\x00", 120);

        $this->assertNotSame([], $this->failures($this->upload($executable, 'nota.pdf')));
    }

    public function test_rejects_html_renamed_to_pdf(): void
    {
        $this->assertNotSame([], $this->failures($this->upload('<html><body>oi</body></html>', 'nota.pdf')));
    }

    public function test_rejects_a_pdf_renamed_to_xml(): void
    {
        $failures = $this->failures($this->fixture('divesa_nfe_80000.pdf', 'nota.xml'));

        $this->assertSame(['O arquivo enviado não é um XML. Envie o PDF ou o XML da nota fiscal.'], $failures);
    }

    public function test_rejects_a_text_file_renamed_to_xml(): void
    {
        $this->assertNotSame([], $this->failures($this->upload("nota fiscal 123\n", 'nota.xml')));
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->assertNotSame([], $this->failures($this->upload('<nfeProc><NFe></nfeProc>', 'nota.xml')));
    }

    public function test_rejects_an_extension_outside_the_list(): void
    {
        $failures = $this->failures($this->fixture('troia_nfe_4250.xml', 'nota.txt'));

        $this->assertSame(['Apenas arquivos PDF ou XML são aceitos.'], $failures);
    }

    public function test_rejects_a_value_that_is_not_an_upload(): void
    {
        $this->assertSame(['Arquivo de nota fiscal inválido.'], $this->failures('nota.pdf'));
    }

    /**
     * @return list<string>
     */
    private function failures(mixed $value): array
    {
        $failures = [];

        (new InvoiceFile)->validate('file', $value, function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        return $failures;
    }

    private function fixture(string $fixtureName, string $clientName): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/invoices/'.$fixtureName),
            $clientName,
            null,
            null,
            true,
        );
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-rule-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    }
}
