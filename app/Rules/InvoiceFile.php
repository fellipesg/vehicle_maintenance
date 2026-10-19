<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

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
            || $this->isWellFormedXml($path);
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

    private function isWellFormedXml(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $content = @file_get_contents($path);

        if (! is_string($content) || trim($content) === '') {
            return false;
        }

        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            return simplexml_load_string($content, options: LIBXML_NONET) !== false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }
}
