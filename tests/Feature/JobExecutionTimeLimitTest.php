<?php

namespace Tests\Feature;

use App\Jobs\Concerns\LimitsExecutionTime;
use App\Jobs\ImportWorkshopProspectsCsv;
use App\Models\User;
use App\Services\Outreach\WorkshopProspectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JobExecutionTimeLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O teto de tempo do job não pode vazar para o resto da suíte.
     *
     * Com QUEUE_CONNECTION=sync o job roda dentro do processo do PHPUnit, e `set_time_limit`
     * limita o processo inteiro, não a chamada. Era assim que a rodada Postgres morria com
     * exit 255 e `Maximum execution time of 300 seconds exceeded` perto do fim da suíte.
     */
    public function test_running_a_job_does_not_cap_the_execution_time_of_the_suite(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        $limitBefore = ini_get('max_execution_time');

        (new ImportWorkshopProspectsCsv("nome,telefone\nZé,123\n", 'errado.csv', $admin->id))
            ->handle(app(WorkshopProspectImporter::class));

        $this->assertSame(
            $limitBefore,
            ini_get('max_execution_time'),
            'Um job rodando sob a fila sync não pode mexer no max_execution_time do processo.',
        );
    }

    /**
     * Fora da suíte o teto continua sendo aplicado — é o worker da Cloud que depende dele, já que
     * o php.ini roda com max_execution_time=0.
     */
    public function test_outside_the_suite_the_job_timeout_is_applied_to_the_process(): void
    {
        $limitBefore = ini_get('max_execution_time');

        $job = new class
        {
            use LimitsExecutionTime;

            public int $timeout = 86400;

            public function apply(): void
            {
                $this->limitExecutionTimeToJobTimeout();
            }
        };

        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $job->apply();

            $this->assertSame('86400', ini_get('max_execution_time'));
        } finally {
            set_time_limit((int) $limitBefore);
            $this->app->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertSame($limitBefore, ini_get('max_execution_time'));
    }

    /**
     * Guarda contra reintrodução: um job novo que copie o `set_time_limit` cru volta a travar a
     * suíte. Quem precisa do teto usa a trait, que sabe se desligar no teste.
     */
    public function test_no_job_calls_set_time_limit_directly(): void
    {
        $offenders = [];

        foreach (glob(app_path('Jobs/*.php')) as $file) {
            if (str_contains((string) file_get_contents($file), 'set_time_limit')) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Use App\Jobs\Concerns\LimitsExecutionTime em vez de set_time_limit direto no job.',
        );
    }
}
