<?php

namespace App\Services\Crlv;

use App\Mail\CrlvImportFailureMail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisa o time quando um CRLV-e válido não é lido: cada falha dessas é um
 * layout de DETRAN que o parser ainda não cobre.
 *
 * O log local fica fora de qualquer janela — é barato e é ele que mostra quantas vezes a pessoa
 * tentou. Só o evento no Sentry e o e-mail ao suporte são limitados, por duas portas: uma janela
 * por falha e por conta (shouldAlert) e um teto global por falha (withinGlobalCap). O contexto não
 * leva o e-mail de quem enviou — o id da conta basta para achar o documento.
 */
class CrlvImportFailureReporter
{
    /**
     * Prefixo da chave da janela por conta.
     *
     * @var string
     */
    private const DEDUPE_KEY_PREFIX = 'crlv-failure:';

    /**
     * Prefixo da chave do teto global, sem a conta.
     *
     * @var string
     */
    private const GLOBAL_KEY_PREFIX = 'crlv-failure-global:';

    /**
     * Janela de silêncio, em minutos, para a mesma falha da mesma conta.
     *
     * @var int
     */
    private const DEDUPE_WINDOW_MINUTES = 60;

    /**
     * Quantos avisos da mesma falha o suporte recebe na janela, somando todas as contas.
     *
     * @var int
     */
    public const GLOBAL_ALERTS_PER_WINDOW = 10;

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
        ];

        $this->logFailure($context);

        $fingerprint = $this->accountFingerprint($exception, $user);

        if (! $this->shouldAlert($fingerprint, $exception)) {
            return;
        }

        $this->captureOnSentry($exception);

        if (! $this->mailSupport($exception, $context)) {
            $this->reopenWindow($fingerprint);
        }
    }

    /**
     * Fora da janela de propósito: o log é local, não custa cota nem caixa de entrada, e é o que
     * diz ao suporte se a pessoa tentou uma vez ou vinte, e com quais arquivos.
     *
     * @param  array<string, mixed>  $context
     */
    private function logFailure(array $context): void
    {
        try {
            Log::warning('CRLV-e não lido.', $context);
        } catch (Throwable $logFailure) {
            // Sem log não há onde registrar a falha do log; quem cadastra já viu o erro na tela.
        }
    }

    /**
     * Classe da exceção + mensagem normalizada + conta: a mensagem é que diz qual layout faltou,
     * e o balde por conta mantém o aviso de outro usuário com o mesmo problema. Nome do arquivo e
     * rota de origem ficam fora de propósito — renomear o PDF ou trocar de tela furaria a janela.
     */
    private function accountFingerprint(Throwable $exception, ?Authenticatable $user): string
    {
        return sha1(implode('|', [
            $exception::class,
            $this->normalizedMessage($exception),
            (string) ($user?->getAuthIdentifier() ?? 'anonimo'),
        ]));
    }

    /**
     * Primeira ocorrência da falha nesta conta e ainda dentro do teto global. Cache fora do ar
     * não pode calar o aviso: a rota já está limitada por throttle:uploads.
     */
    private function shouldAlert(string $fingerprint, Throwable $exception): bool
    {
        try {
            $firstForAccount = Cache::add(
                self::DEDUPE_KEY_PREFIX.$fingerprint,
                true,
                now()->addMinutes(self::DEDUPE_WINDOW_MINUTES),
            );

            return $firstForAccount && $this->withinGlobalCap($exception);
        } catch (Throwable $cacheFailure) {
            Log::warning('Falha ao deduplicar aviso de CRLV-e.', [
                'erro' => $cacheFailure->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Teto por falha somando todas as contas: um layout novo de DETRAN que chega a centenas de
     * pessoas no mesmo dia rende os primeiros avisos, não uma caixa de entrada inteira. A janela
     * por conta sozinha não segura isso, porque a conta entra na chave.
     */
    private function withinGlobalCap(Throwable $exception): bool
    {
        $key = self::GLOBAL_KEY_PREFIX.sha1($exception::class.'|'.$this->normalizedMessage($exception));

        return (bool) RateLimiter::attempt(
            $key,
            self::GLOBAL_ALERTS_PER_WINDOW,
            static fn (): bool => true,
            self::DEDUPE_WINDOW_MINUTES * 60,
        );
    }

    private function normalizedMessage(Throwable $exception): string
    {
        return Str::lower(Str::squish($exception->getMessage()));
    }

    /**
     * Despacho que falhou não gasta a janela: sem isso, um tropeço da fila na primeira ocorrência
     * de um layout novo calava o aviso por uma hora e o suporte nunca ficava sabendo. O teto
     * global não é devolvido — é um crédito em dez, e a rota já está limitada.
     */
    private function reopenWindow(string $fingerprint): void
    {
        try {
            Cache::forget(self::DEDUPE_KEY_PREFIX.$fingerprint);
        } catch (Throwable $cacheFailure) {
            Log::warning('Falha ao reabrir a janela de aviso de CRLV-e.', [
                'erro' => $cacheFailure->getMessage(),
            ]);
        }
    }

    /**
     * O handler da aplicação já entrega exceções reportadas ao Sentry
     * (Integration::handles, em bootstrap/app.php).
     */
    private function captureOnSentry(Throwable $exception): void
    {
        try {
            report($exception);
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
     * @return bool Se o aviso entrou na fila.
     */
    private function mailSupport(Throwable $exception, array $context): bool
    {
        try {
            Mail::to((string) config('legal.support_email'))
                ->queue(new CrlvImportFailureMail(
                    reason: $exception->getMessage(),
                    context: $context,
                ));

            return true;
        } catch (Throwable $mailFailure) {
            // O aviso é secundário: quem está cadastrando já recebeu o erro na tela.
            Log::warning('Falha ao avisar o suporte sobre erro de CRLV-e.', [
                'erro' => $mailFailure->getMessage(),
            ]);

            return false;
        }
    }
}
