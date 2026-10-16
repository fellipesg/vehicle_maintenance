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
