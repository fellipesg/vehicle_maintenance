<?php

namespace App\Services\Crlv;

use App\Mail\CrlvImportFailureMail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisa o time quando um CRLV-e válido não é lido: cada falha dessas é um
 * layout de DETRAN que o parser ainda não cobre.
 *
 * O aviso é um só por falha e por conta dentro da janela (shouldAlert): quem reenvia o mesmo
 * documento em sequência não enche a caixa do suporte nem a cota do Sentry. O contexto não leva
 * o e-mail de quem enviou — o id da conta basta para achar o documento, e o aviso vai para o log
 * e para fora da aplicação.
 */
class CrlvImportFailureReporter
{
    /**
     * Prefixo da chave de deduplicação no cache.
     *
     * @var string
     */
    private const DEDUPE_KEY_PREFIX = 'crlv-failure:';

    /**
     * Janela de silêncio, em minutos, para a mesma falha da mesma conta.
     *
     * @var int
     */
    private const DEDUPE_WINDOW_MINUTES = 60;

    public function report(
        Throwable $exception,
        ?UploadedFile $file,
        ?Authenticatable $user,
        string $origin,
    ): void {
        if (! $this->shouldAlert($exception, $user)) {
            return;
        }

        $context = [
            'origem' => $origin,
            'arquivo' => $file?->getClientOriginalName(),
            'tamanho_kb' => $file ? (int) round($file->getSize() / 1024) : null,
            'usuario_id' => $user?->getAuthIdentifier(),
        ];

        $this->captureOnSentry($exception, $context);
        $this->mailSupport($exception, $context);
    }

    /**
     * Primeira ocorrência da falha na janela. A chave é a classe da exceção + a mensagem
     * normalizada + a conta: a mensagem é que diz qual layout faltou, e o balde por conta
     * mantém o aviso de outro usuário com o mesmo problema. Nome do arquivo e rota de origem
     * ficam fora de propósito — renomear o PDF ou trocar de tela furaria a janela.
     */
    private function shouldAlert(Throwable $exception, ?Authenticatable $user): bool
    {
        $fingerprint = sha1(implode('|', [
            $exception::class,
            Str::lower(Str::squish($exception->getMessage())),
            (string) ($user?->getAuthIdentifier() ?? 'anonimo'),
        ]));

        try {
            return Cache::add(
                self::DEDUPE_KEY_PREFIX.$fingerprint,
                true,
                now()->addMinutes(self::DEDUPE_WINDOW_MINUTES),
            );
        } catch (Throwable $cacheFailure) {
            // Cache fora do ar não pode calar o aviso: avisa e segue.
            Log::warning('Falha ao deduplicar aviso de CRLV-e.', [
                'erro' => $cacheFailure->getMessage(),
            ]);

            return true;
        }
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
     * O aviso vai para a fila (CrlvImportFailureMail é ShouldQueue, com tries/backoff): o
     * usuário já recebeu o erro na tela e não espera o provedor de e-mail.
     *
     * @param  array<string, mixed>  $context
     */
    private function mailSupport(Throwable $exception, array $context): void
    {
        try {
            Mail::to((string) config('legal.support_email'))
                ->queue(new CrlvImportFailureMail(
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
