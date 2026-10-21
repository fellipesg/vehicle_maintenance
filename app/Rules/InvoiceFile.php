<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use XMLReader;

/**
 * A nota fiscal só é aceita quando o conteúdo do arquivo corresponde ao tipo declarado.
 * O nome e o MIME informados no upload são escolhidos por quem envia, então uma checagem
 * só pela extensão aceita qualquer arquivo renomeado para .pdf.
 */
class InvoiceFile implements ValidationRule
{
    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['pdf', 'xml'];

    /**
     * Teto de notas por requisição, igual em toda superfície que aceita o array `invoices`
     * (API e web, nos três portais). Uma OS real traz a nota das peças e a da mão de obra,
     * cada uma podendo vir como PDF e XML: cinco NF-e nos dois formatos já é folga. Sem o
     * teto, uma requisição com dezenas de arquivos força esta regra a ler todos (MIME por
     * conteúdo, cabeçalho do PDF e uma passada de XMLReader) antes de qualquer resposta.
     */
    public const MAX_FILES_PER_REQUEST = 10;

    /**
     * MIME aceitos de imediato para XML. Uma NF-e sem a declaração `<?xml ?>` é detectada
     * como text/plain (tests/fixtures/invoices/divesa_nfe_33893.xml), por isso o XML também
     * é aceito quando o conteúdo é um documento XML bem formado.
     *
     * @var list<string>
     */
    private const XML_MIME_TYPES = ['text/xml', 'application/xml'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Arquivo de nota fiscal inválido.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $fail('Apenas arquivos PDF ou XML são aceitos.');

            return;
        }

        $path = $value->getRealPath() ?: $value->path();

        $matchesDeclaredType = $extension === 'pdf'
            ? $this->isPdf($value, $path)
            : $this->isXml($value, $path);

        if (! $matchesDeclaredType) {
            $fail($extension === 'pdf'
                ? 'O arquivo enviado não é um PDF. Envie o PDF ou o XML da nota fiscal.'
                : 'O arquivo enviado não é um XML. Envie o PDF ou o XML da nota fiscal.');
        }
    }

    private function isPdf(UploadedFile $file, string $path): bool
    {
        return $this->mimeType($file) === 'application/pdf'
            || $this->startsWithPdfHeader($path);
    }

    private function isXml(UploadedFile $file, string $path): bool
    {
        return in_array($this->mimeType($file), self::XML_MIME_TYPES, true)
            || $this->isXmlDocument($path);
    }

    /**
     * MIME do conteúdo do arquivo (não o enviado pelo cliente). Em teste, o upload falso
     * do Laravel deriva o MIME do nome, como a própria regra `mimes` do framework.
     */
    private function mimeType(UploadedFile $file): string
    {
        try {
            return strtolower((string) $file->getMimeType());
        } catch (\Throwable) {
            return '';
        }
    }

    private function startsWithPdfHeader(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $header = @file_get_contents($path, false, null, 0, 5);

        return is_string($header) && str_starts_with($header, '%PDF-');
    }

    /**
     * Documento XML de verdade. Os chamadores põem `max:10240` antes desta regra, com
     * `bail`, então o arquivo que chega aqui já está dentro do limite de tamanho.
     */
    private function isXmlDocument(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $content = @file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            return false;
        }

        if ($this->parsesAsXml($content)) {
            return true;
        }

        /*
         * Sem a declaração de XML o libxml assume UTF-8 e recusa a NF-e gravada em
         * ISO-8859-1, onde o acento é um byte inválido. Converte e tenta de novo, senão
         * uma nota legítima (o layout sem declaração existe: divesa_nfe_33893.xml) seria
         * recusada e a OS que exige nota fiscal não poderia ser salva.
         */
        if (! mb_check_encoding($content, 'UTF-8')) {
            $asUtf8 = @mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');

            return is_string($asUtf8) && $this->parsesAsXml($asUtf8);
        }

        return false;
    }

    /**
     * Percorre o documento em fluxo, sem montar a árvore inteira: uma NF-e de 10 MB
     * custaria mais de 100 MB de memória de libxml, que não entra no memory_limit.
     */
    private function parsesAsXml(string $content): bool
    {
        $useInternalErrors = libxml_use_internal_errors(true);
        $reader = new XMLReader;
        $opened = false;

        try {
            $opened = $reader->XML($content, null, LIBXML_NONET);

            if ($opened !== true) {
                return false;
            }

            $sawElement = false;

            while ($reader->read()) {
                $sawElement = $sawElement || $reader->nodeType === XMLReader::ELEMENT;
            }

            foreach (libxml_get_errors() as $error) {
                if ($error->level >= LIBXML_ERR_ERROR) {
                    return false;
                }
            }

            return $sawElement;
        } catch (\Throwable) {
            return false;
        } finally {
            if ($opened === true) {
                $reader->close();
            }

            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }
}
