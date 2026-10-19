<?php

namespace Tests\Unit\Services\Crlv;

use App\Mail\CrlvImportFailureMail;
use App\Models\User;
use App\Services\Crlv\CrlvImportFailureReporter;
use App\Services\Crlv\CrlvParseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * As duas portas do aviso de CRLV-e não lido vistas pelo serviço, onde dá para fazer o despacho
 * do e-mail falhar: janela por conta e teto global por falha.
 */
class CrlvImportFailureReporterTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Estrutura do CRLV-e não reconhecida.';

    /**
     * Despacho que falhou não pode gastar a janela: era isso que calava o aviso por uma hora
     * quando a fila tropeçava na primeira ocorrência de um layout novo.
     */
    public function test_failed_dispatch_reopens_the_window(): void
    {
        Exceptions::fake();

        $dispatches = 0;

        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('queue')->andReturnUsing(function () use (&$dispatches): never {
            $dispatches++;

            throw new RuntimeException('fila fora do ar');
        });

        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('to')->andReturn($pending);
        Mail::swap($manager);

        $user = User::factory()->asUser()->create();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->reportFailure($user);
        }

        $this->assertSame(2, $dispatches, 'A janela não reabriu depois de o despacho falhar.');
    }

    /**
     * Despacho que deu certo fecha a janela: a segunda tentativa idêntica não repete o aviso.
     */
    public function test_successful_dispatch_keeps_the_window_closed(): void
    {
        Mail::fake();
        Exceptions::fake();

        $user = User::factory()->asUser()->create();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->reportFailure($user);
        }

        Mail::assertQueuedCount(1);
        Exceptions::assertReportedCount(1);
    }

    /**
     * A janela por conta não segura um layout de DETRAN que chega a muita gente, porque a conta
     * entra na chave: o teto global por falha é que para a enxurrada.
     */
    public function test_global_cap_limits_a_failure_that_hits_many_accounts(): void
    {
        Mail::fake();
        Exceptions::fake();

        $accounts = CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW + 3;

        for ($account = 1; $account <= $accounts; $account++) {
            $this->reportFailure(User::factory()->asUser()->create());
        }

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW);
        Exceptions::assertReportedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW);
    }

    /**
     * O teto é por falha: outra mensagem tem crédito próprio, senão um layout popular esconderia
     * o próximo layout a aparecer.
     */
    public function test_global_cap_is_per_failure_message(): void
    {
        Mail::fake();
        Exceptions::fake();

        $exhausted = CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW + 2;

        for ($account = 1; $account <= $exhausted; $account++) {
            $this->reportFailure(User::factory()->asUser()->create());
        }

        $this->reportFailure(User::factory()->asUser()->create(), 'Placa não encontrada no CRLV-e.');

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW + 1);
        Mail::assertQueued(
            CrlvImportFailureMail::class,
            fn (CrlvImportFailureMail $mail) => $mail->reason === 'Placa não encontrada no CRLV-e.'
        );
    }

    /**
     * Cache fora do ar não pode calar o aviso: a rota já está limitada por throttle:uploads.
     */
    public function test_cache_failure_lets_the_alert_through(): void
    {
        Mail::fake();
        Exceptions::fake();

        Cache::shouldReceive('add')->andThrow(new RuntimeException('cache fora do ar'));

        $user = User::factory()->asUser()->create();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->reportFailure($user);
        }

        Mail::assertQueuedCount(2);
    }

    private function reportFailure(User $user, string $reason = self::REASON): void
    {
        app(CrlvImportFailureReporter::class)->report(
            new CrlvParseException($reason),
            UploadedFile::fake()->create('CRLV-e.pdf', 120, 'application/pdf'),
            $user,
            'user.vehicles.create',
        );
    }
}
