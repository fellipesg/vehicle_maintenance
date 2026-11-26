<?php

namespace App\Jobs\Concerns;

/**
 * Dá ao processo do worker o mesmo teto de tempo que o job declara em $timeout.
 *
 * Requer a propriedade `public int $timeout` no job que usa a trait.
 */
trait LimitsExecutionTime
{
    /**
     * Aplica ao processo atual o teto de tempo do job — exceto durante a suíte.
     *
     * O `php.ini` do contêiner roda com `max_execution_time=0`, então sem isto um job travado
     * (a geração de PDF é a candidata) só pararia quando o worker da Cloud o matasse por fora.
     *
     * Na suíte o teto não pode ser aplicado: os testes rodam com `QUEUE_CONNECTION=sync`, o job
     * executa dentro do processo do PHPUnit e `set_time_limit` crava o limite no **resto da
     * rodada**, não só no job. A rodada Postgres passa dos 300 s e morria com
     * `Maximum execution time of 300 seconds exceeded` em quem estivesse rodando na hora
     * (medido: exit 255 aos 96-98% da suíte, dentro do dompdf).
     */
    protected function limitExecutionTimeToJobTimeout(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }
    }
}
