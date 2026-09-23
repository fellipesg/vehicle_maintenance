<?php

namespace App\Services\Crlv;

use App\Mail\CrlvImportFailureMail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa o time quando um CRLV-e válido não é lido: cada falha dessas é um
 * layout de DETRAN que o parser ainda não cobre.
 */
class CrlvImportFailureReporter
{
    public function report(
        Throwable $exception,
        ?UploadedFile $file,
        ?Authenticatable $user,
        string $origin,
    ): void {
        $context = [
            'origem' => $origin,
            'arquivo' => $file?->getClientOriginalName(),
            'tamanho_kb' => $file ? (int) round($file->getSize() / 1024) : null,
            'usuario_id' => $user?->getAuthIdentifier(),
            'usuario_email' => $user?->getAttribute('email'),
        ];

        $this->captureOnSentry($exception, $context);
        $this->mailSupport($exception, $context);
    }

    /**
     * O handler da aplicação já entrega exceções reportadas ao Sentry
     * (Integration::handles, em bootstrap/app.php).
     *
     * @param  array<string, mixed>  $context
     */
    private function captureOnSentry(Throwable $exception, array $context): void
    {
        try {
            report($exception);
            Log::warning('CRLV-e não lido.', $context);
        } catch (Throwable $reportFailure) {
            Log::warning('Falha ao reportar erro de CRLV-e.', [
                'erro' => $reportFailure->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function mailSupport(Throwable $exception, array $context): void
    {
        try {
            Mail::to((string) config('legal.support_email'))
                ->send(new CrlvImportFailureMail(
                    reason: $exception->getMessage(),
                    context: $context,
                ));
        } catch (Throwable $mailFailure) {
            // O aviso é secundário: quem está cadastrando já recebeu o erro na tela.
            Log::warning('Falha ao avisar o suporte sobre erro de CRLV-e.', [
                'erro' => $mailFailure->getMessage(),
            ]);
        }
    }
}
