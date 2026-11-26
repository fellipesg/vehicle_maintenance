<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * "Este upload é um PDF de verdade?", decidido pelo **conteúdo** do arquivo.
 *
 * O nome e o MIME declarados no upload são escolhidos por quem envia, então uma checagem só pela
 * extensão aceita qualquer arquivo renomeado para `.pdf`. É a mesma razão de
 * App\Support\UploadedImageExtension existir para imagens (.ai/rules/models.md, "A extensão também
 * não é do cliente: ela sai do conteúdo").
 *
 * Esta classe é a única resposta da casa para o conteúdo PDF: App\Rules\InvoiceFile (a nota fiscal) e
 * App\Services\Inspection\VehicleInspectionService (o laudo da vistoria cautelar) chamam daqui, para
 * que uma segunda cópia da conta não se afaste da primeira. O lado XML continua em `InvoiceFile`: é
 * NF-e com parsing em fluxo e a volta de encoding, que só a nota tem.
 *
 * O serviço confere por conta própria em vez de confiar no `mimes:pdf` do FormRequest: a regra de
 * validação protege **a tela que a declara**, e o serviço é quem grava o caminho no storage — quem o
 * chamar de outro lugar (um comando, um recurso de API futuro) não deve poder escolher a extensão do
 * objeto gravado.
 */
class UploadedPdfFile
{
    /**
     * Conteúdo de PDF: o MIME lido do arquivo (finfo), ou o cabeçalho `%PDF-`.
     *
     * Os dois caminhos existem porque o finfo de alguns PDFs gerados por scanner devolve
     * `application/octet-stream`, e porque no upload falso do Laravel o MIME é derivado do nome —
     * é o mesmo par que a nota fiscal usa desde o começo.
     */
    public static function matches(UploadedFile $file): bool
    {
        return self::mimeType($file) === 'application/pdf'
            || self::startsWithPdfHeader($file->getRealPath() ?: $file->path());
    }

    /**
     * MIME do conteúdo do arquivo (não o enviado pelo cliente). Em teste, o upload falso do Laravel
     * deriva o MIME do nome, como a própria regra `mimes` do framework.
     */
    private static function mimeType(UploadedFile $file): string
    {
        try {
            return strtolower((string) $file->getMimeType());
        } catch (\Throwable) {
            return '';
        }
    }

    private static function startsWithPdfHeader(string $path): bool
    {
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        $header = @file_get_contents($path, false, null, 0, 5);

        return is_string($header) && str_starts_with($header, '%PDF-');
    }
}
