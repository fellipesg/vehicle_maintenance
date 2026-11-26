<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Faz o /up exercitar banco e fila, em vez de só provar que o PHP respondeu.
 *
 * A rota de health do framework (`health: '/up'` em bootstrap/app.php) despacha DiagnosingHealth,
 * captura o que escapar daqui, chama report() e responde 500. Sem nenhum listener ela devolvia 200
 * com o Neon fora do ar ou com a fila sem worker.
 *
 * O endpoint é público e sem autenticação, então o que este listener lança é propositalmente
 * genérico e sem número: nenhuma mensagem diz host, banco, usuário, versão, nem quantos jobs
 * estão parados. O detalhe real viaja como exceção anterior, que report() entrega ao Sentry.
 */
class VerifyApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->verifyDatabase();
        $this->verifyQueue();
    }

    /**
     * Uma ida e volta ao banco padrão: `getPdo()` sozinho não prova que o servidor responde.
     */
    protected function verifyDatabase(): void
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable $exception) {
            throw new RuntimeException('Database check failed.', previous: $exception);
        }
    }

    /**
     * A fila está parada quando sobrou job vencido que worker nenhum reservou.
     *
     * `markJobAsReserved` só escreve reserved_at — não empurra available_at —, então job em
     * execução (o PDF leva até 300 s) tem reserved_at preenchido e fica fora da conta. Quem conta
     * é job já vencido e livre: é o sintoma de worker derrubado, que é a falha real que este app
     * tem (ver .ai/rules/jobs.md: o worker `queue:work database` é um processo manual).
     *
     * `exists()` em vez de `count()`: para o endpoint basta saber se existe, e assim nenhum
     * número sobre volume chega a ser lido.
     */
    protected function verifyQueue(): void
    {
        $connection = config('queue.default');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return;
        }

        $cutoff = now()
            ->subMinutes((int) config('health.queue_backlog_minutes'))
            ->getTimestamp();

        try {
            $stalled = DB::connection(config("queue.connections.{$connection}.connection"))
                ->table(config("queue.connections.{$connection}.table", 'jobs'))
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $cutoff)
                ->exists();
        } catch (Throwable $exception) {
            throw new RuntimeException('Queue check failed.', previous: $exception);
        }

        if ($stalled) {
            throw new RuntimeException('Queue is not being drained.');
        }
    }
}
