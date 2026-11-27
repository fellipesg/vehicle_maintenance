<?php

namespace Tests\Unit\Inspection;

use App\Support\UploadedPdfFile;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A extensão do laudo gravado sai do **conteúdo**, nunca do nome que o cliente enviou.
 *
 * O serviço confiava primeiro em `getClientOriginalExtension()` e só caía em `guessExtension()` se o
 * nome não dissesse "pdf" — então um arquivo qualquer renomeado para `.pdf` passava pelo serviço e ia
 * para o storage com `.pdf`. A checagem por conteúdo vivia só no `mimes:pdf` do FormRequest, que
 * protege a tela que o declara e não quem chama o serviço de outro lugar. É a mesma razão de
 * App\Support\UploadedImageExtension existir para imagens (.ai/rules/models.md) e de
 * App\Rules\InvoiceFile ler o arquivo da nota.
 */
class InspectionReportContentTest extends TestCase
{
    public function test_a_real_pdf_is_accepted(): void
    {
        $this->assertTrue(UploadedPdfFile::matches($this->upload("%PDF-1.4\n1 0 obj\n", 'laudo.pdf')));
    }

    /**
     * A NF-e em PDF da fixture é o PDF de verdade que a casa já usa nos testes da nota.
     */
    public function test_the_pdf_fixture_is_accepted(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/divesa_nfe_80000.pdf'),
            'laudo.pdf',
            null,
            null,
            true,
        );

        $this->assertTrue(UploadedPdfFile::matches($file));
    }

    public function test_html_renamed_to_pdf_is_refused(): void
    {
        $this->assertFalse(UploadedPdfFile::matches(
            $this->upload('<html><body>oi</body></html>', 'laudo.pdf'),
        ));
    }

    public function test_a_shell_script_renamed_to_pdf_is_refused(): void
    {
        $this->assertFalse(UploadedPdfFile::matches(
            $this->upload("#!/bin/sh\necho pwned\n", 'laudo.pdf'),
        ));
    }

    public function test_an_executable_renamed_to_pdf_is_refused(): void
    {
        $executable = "\x7fELF\x02\x01\x01\x00".str_repeat("\x00", 120);

        $this->assertFalse(UploadedPdfFile::matches($this->upload($executable, 'laudo.pdf')));
    }

    /**
     * Um PDF cujo finfo devolve application/octet-stream (acontece com saída de scanner) continua
     * aceito pelo cabeçalho: os dois caminhos existem de propósito, como na nota fiscal.
     */
    public function test_a_pdf_without_a_recognised_mime_is_accepted_by_its_header(): void
    {
        $file = $this->upload("%PDF-1.7\n\x00\x01\x02binário", 'laudo.pdf');

        $this->assertTrue(UploadedPdfFile::matches($file));
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'inspection-report-');
        file_put_contents($path, $content);

        // Um UploadedFile de verdade (não o fake), senão o MIME sairia do nome e a forja passaria —
        // é o mesmo cuidado de tests/Unit/Rules/InvoiceFileTest.
        return new UploadedFile($path, $clientName, null, null, true);
    }
}
