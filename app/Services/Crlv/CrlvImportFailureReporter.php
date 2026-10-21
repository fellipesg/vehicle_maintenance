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
 * por falha e por conta (isFirstFailureInWindow) e um teto global por falha (withinGlobalCap).
 * Aviso que o teto global engoliu devolve a janela da conta e sai no log: nenhuma falha real fica
 * invisível. O contexto não leva o e-mail de quem enviou nem o nome do arquivo escolhido pelo
 * cliente — só o id da conta, a extensão, o tamanho e um hash curto do conteúdo.
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

    /**
     * Mensagem do log quando o teto global cala o aviso. O suporte conta por ela quantas falhas
     * ficaram sem e-mail na janela.
     *
     * @var string
     */
    public const GLOBAL_CAP_LOG_MESSAGE = 'Aviso de CRLV-e não enviado: teto global da janela atingido.';

    /**
     * Caracteres do hash do conteúdo que vão no contexto: o bastante para juntar envios do mesmo
     * PDF sem virar um identificador do documento.
     *
     * @var int
     */
    private const FILE_HASH_LENGTH = 12;

    public function report(
        Throwable $exception,
        ?UploadedFile $file,
        ?Authenticatable $user,
        string $origin,
    ): void {
        $context = [
            'origem' => $origin,
            'extensao' => $this->fileExtension($file),
            'tamanho_kb' => $file ? (int) round($file->getSize() / 1024) : null,
            'hash_arquivo' => $this->fileHash($file),
            'usuario_id' => $user?->getAuthIdentifier(),
        ];

        $this->logFailure($context);

        $fingerprint = $this->accountFingerprint($exception, $user);

        if (! $this->isFirstFailureInWindow($fingerprint)) {
            return;
        }

        if (! $this->withinGlobalCap($exception)) {
            $this->reopenWindow($fingerprint);
            $this->logGlobalCapSuppression($exception, $context);

            return;
        }

        $this->captureOnSentry($exception);

        if (! $this->mailSupport($exception, $context)) {
            $this->reopenWindow($fingerprint);
        }
    }

    /**
     * Fora da janela de propósito: o log é local, não custa cota nem caixa de entrada, e é o que
     * diz ao suporte se a pessoa tentou uma vez ou vinte.
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
     * Primeira ocorrência da falha nesta conta, e é esta chamada que fecha a janela. O teto global
     * é consultado depois, só para quem passou por aqui: assim uma conta que reenvia o mesmo PDF
     * em loop não gasta os créditos globais de outras contas. Cache fora do ar não pode calar o
     * aviso: a rota já está limitada por throttle:uploads.
     */
    private function isFirstFailureInWindow(string $fingerprint): bool
    {
        try {
            return (bool) Cache::add(
                self::DEDUPE_KEY_PREFIX.$fingerprint,
                true,
                now()->addMinutes(self::DEDUPE_WINDOW_MINUTES),
            );
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
     * por conta sozinha não segura isso, porque a conta entra na chave. Teto estourado não gasta
     * crédito (RateLimiter::attempt não incrementa), então reavaliar a próxima falha é barato e
     * não empurra a janela global para frente.
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
     * Aviso que o teto global engoliu fica no log com a falha inteira: é por ele que se vê que o
     * suporte parou de receber e-mail porque o teto encheu, e não porque as falhas acabaram.
     *
     * @param  array<string, mixed>  $context
     */
    private function logGlobalCapSuppression(Throwable $exception, array $context): void
    {
        try {
            Log::warning(self::GLOBAL_CAP_LOG_MESSAGE, $context + [
                'falha' => $exception::class,
                'motivo' => $exception->getMessage(),
                'teto_por_janela' => self::GLOBAL_ALERTS_PER_WINDOW,
            ]);
        } catch (Throwable $logFailure) {
            // Sem log não há onde registrar a supressão; a falha em si já foi registrada acima.
        }
    }

    /**
     * Devolve a janela da conta quando o aviso não saiu: despacho que falhou (sem isso, um tropeço
     * da fila na primeira ocorrência de um layout novo calava o aviso por uma hora e o suporte
     * nunca ficava sabendo) e aviso barrado pelo teto global (sem isso, a conta suprimida no
     * minuto em que o teto encheu ficava calada o resto da hora, mesmo depois de o teto liberar, e
     * uma falha real de CRLV-e sumia). O teto global não é devolvido — é um crédito em dez, e a
     * rota já está limitada.
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
     * Extensão minúscula e sem pontuação, deduzida do conteúdo quando dá, senão a do nome enviado.
     * O nome que o cliente escolheu nunca entra no contexto: pode trazer nome ou CPF da pessoa
     * (LGPD, .ai/rules/models.md), e o que falta ao suporte é o layout do DETRAN.
     */
    private function fileExtension(?UploadedFile $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        try {
            $extension = (string) ($file->guessExtension() ?: $file->getClientOriginalExtension());
        } catch (Throwable $extensionFailure) {
            $extension = (string) $file->getClientOriginalExtension();
        }

        $extension = preg_replace('/[^a-z0-9]/', '', Str::lower($extension)) ?? '';

        return $extension !== '' ? $extension : null;
    }

    /**
     * Hash curto do conteúdo em vez do nome do arquivo: dois envios do mesmo PDF têm a mesma
     * impressão, o suporte junta os avisos e pede o documento a quem enviou, e nada no aviso
     * identifica a pessoa.
     */
    private function fileHash(?UploadedFile $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        try {
            $path = $file->getRealPath() ?: $file->path();

            if (! is_string($path) || ! is_file($path)) {
                return null;
            }

            $hash = hash_file('sha256', $path);

            return is_string($hash) ? substr($hash, 0, self::FILE_HASH_LENGTH) : null;
        } catch (Throwable $hashFailure) {
            return null;
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
