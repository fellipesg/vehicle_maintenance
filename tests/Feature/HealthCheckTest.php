<?php

namespace Tests\Feature;

use App\Listeners\VerifyApplicationHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Aponta a conexão padrão para um sqlite que não existe e devolve o estado anterior depois.
     *
     * Trocar `database.default` em vez de mockar a facade é de propósito: `DB::shouldReceive`
     * substitui também o binding `db` do container, e o RefreshDatabase usa esse binding no
     * rollback do teardown.
     */
    private function withUnreachableDatabase(callable $callback): mixed
    {
        $original = config('database.default');

        config([
            'database.connections.health_unreachable' => [
                'driver' => 'sqlite',
                'database' => '/nao/existe/revisalog-health.sqlite',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'database.default' => 'health_unreachable',
        ]);

        try {
            return $callback();
        } finally {
            config(['database.default' => $original]);
            DB::purge('health_unreachable');
        }
    }

    public function test_up_is_healthy_when_the_database_answers_and_the_queue_is_drained(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertSee('Application up', false);
    }

    public function test_up_fails_when_the_database_is_unavailable(): void
    {
        $response = $this->withUnreachableDatabase(fn () => $this->get('/up'));

        $response->assertStatus(500)
            ->assertSee('experiencing problems', false);
    }

    public function test_up_fails_when_a_due_job_is_sitting_unreserved(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(60)->getTimestamp(),
            'created_at' => now()->subMinutes(60)->getTimestamp(),
        ]);

        $this->get('/up')->assertStatus(500);
    }

    public function test_a_job_being_processed_or_waiting_on_its_delay_keeps_up_healthy(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            // Em execução: markJobAsReserved só escreve reserved_at, e o PDF leva até 300 s.
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 1,
                'reserved_at' => now()->subMinutes(60)->getTimestamp(),
                'available_at' => now()->subMinutes(60)->getTimestamp(),
                'created_at' => now()->subMinutes(60)->getTimestamp(),
            ],
            // outreach:send enfileira com atraso aleatório de até 600 s.
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->addMinutes(9)->getTimestamp(),
                'created_at' => now()->getTimestamp(),
            ],
        ]);

        $this->get('/up')->assertOk();
    }

    public function test_the_queue_check_is_skipped_when_the_queue_driver_is_not_database(): void
    {
        config(['queue.default' => 'sync']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(60)->getTimestamp(),
            'created_at' => now()->subMinutes(60)->getTimestamp(),
        ]);

        $this->get('/up')->assertOk();
    }

    /**
     * O /up é público e sem autenticação: a resposta não pode virar painel de diagnóstico.
     */
    public function test_a_failing_check_tells_the_public_nothing_about_the_infrastructure(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            throw new RuntimeException(
                'SQLSTATE[08006] connection to server at "ep-segredo-123.us-east-2.aws.neon.tech", '
                .'port 5432 failed: FATAL: password authentication failed for user "neondb_owner"',
            );
        });

        $response = $this->get('/up')->assertStatus(500);

        foreach (['neon.tech', 'neondb_owner', 'SQLSTATE', 'ep-segredo-123'] as $secret) {
            $response->assertDontSee($secret, false);
        }
    }

    /**
     * A mensagem que o listener lança é fixa e genérica, para nunca carregar host, caminho, usuário
     * nem credencial do driver. O detalhe real viaja como exceção anterior, que o report() da rota
     * de health entrega ao Sentry.
     */
    public function test_the_listener_replaces_the_driver_message_with_a_generic_one(): void
    {
        $exception = $this->withUnreachableDatabase(function (): RuntimeException {
            try {
                (new VerifyApplicationHealth)->handle(new DiagnosingHealth);
            } catch (RuntimeException $exception) {
                return $exception;
            }

            $this->fail('O listener tinha de recusar a conexão quebrada.');
        });

        $this->assertSame('Database check failed.', $exception->getMessage());
        $this->assertNotNull($exception->getPrevious());
        $this->assertStringContainsString('/nao/existe', (string) $exception->getPrevious()->getMessage());
    }

    /**
     * Nenhum dado de infraestrutura nem número de volume na resposta pública saudável.
     */
    public function test_the_healthy_response_carries_no_infrastructure_detail(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->addMinutes(5)->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $body = $this->get('/up')->assertOk()->getContent();

        $this->assertStringNotContainsString(app()->version(), $body);
        $this->assertStringNotContainsString(PHP_VERSION, $body);
        $this->assertStringNotContainsString('jobs', $body);
        $this->assertStringNotContainsString(gethostname() ?: 'sem-host', $body);
    }
}
