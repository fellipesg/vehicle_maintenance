<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\AppStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Abre a nota fiscal de uma manutenção para quem pode ver a manutenção (InvoicePolicy::view),
 * em qualquer portal. Os portais nunca linkam o caminho do storage: lá a nota é um arquivo
 * público (disco local) ou uma URL assinada compartilhável (S3/R2), e quem tiver o link abre a
 * NF de outra conta.
 *
 * O arquivo é do cliente, então sai com os mesmos cabeçalhos defensivos do servidor de arquivos
 * do Laravel (sandbox + nosniff) e só o PDF abre na aba; XML e qualquer outra extensão baixam.
 */
class InvoiceDownloadController extends Controller
{
    /**
     * Extensões que o navegador pode abrir na aba, com o tipo que vai no Content-Type.
     */
    private const INLINE_TYPES = [
        'pdf' => 'application/pdf',
    ];

    private const DOWNLOAD_TYPES = [
        'xml' => 'application/xml',
    ];

    public function __invoke(Invoice $invoice): BinaryFileResponse
    {
        $invoice->loadMissing('maintenance');

        Gate::authorize('view', $invoice);

        $path = (string) $invoice->file_path;

        if ($path === '') {
            abort(404);
        }

        $copy = AppStorage::localCopy($path);

        if ($copy === null) {
            abort(404);
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $inline = array_key_exists($extension, self::INLINE_TYPES);

        $response = response()->download(
            $copy['path'],
            $this->downloadName($invoice, $path),
            [
                'Content-Type' => self::INLINE_TYPES[$extension]
                    ?? self::DOWNLOAD_TYPES[$extension]
                    ?? 'application/octet-stream',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            ],
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );

        if ($copy['temporary']) {
            $response->deleteFileAfterSend();
        }

        return $response;
    }

    /**
     * Nome que o cliente enviou, sem o que o Content-Disposition não aceita (separadores de
     * caminho). O caminho no storage é aleatório, então ele serve só para exibir.
     */
    private function downloadName(Invoice $invoice, string $path): string
    {
        $name = trim(str_replace(['/', '\\'], '', (string) $invoice->file_name));

        return $name !== '' ? $name : basename($path);
    }
}
