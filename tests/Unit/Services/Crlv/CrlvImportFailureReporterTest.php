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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * O teto global mora no cache, então ele cai junto com o store. Nem calar o aviso, nem derrubar
     * quem está cadastrando: report() roda dentro do catch do controller (ImportsVehicleFromCrlv),
     * e uma exceção aqui trocava o erro na tela por um 500.
     */
    public function test_global_cap_failure_lets_the_alert_through(): void
    {
        Mail::fake();
        Exceptions::fake();
        Log::spy();

        RateLimiter::shouldReceive('attempt')->andThrow(new RuntimeException('cache fora do ar'));

        $this->reportFailure(User::factory()->asUser()->create());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Falha ao aplicar o teto global do aviso de CRLV-e.'
                && $context['erro'] === 'cache fora do ar')
            ->once();

        Mail::assertQueuedCount(1);
        Exceptions::assertReportedCount(1);
    }

    /**
     * Aviso que o teto global engoliu não pode sumir sem rastro: o log é o que mostra que o suporte
     * parou de receber e-mail porque o teto encheu, não porque as falhas acabaram.
     */
    public function test_global_cap_suppression_is_logged(): void
    {
        Mail::fake();
        Exceptions::fake();

        $this->exhaustGlobalCap();

        Log::spy();

        $suppressed = User::factory()->asUser()->create();
        $this->reportFailure($suppressed);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === CrlvImportFailureReporter::GLOBAL_CAP_LOG_MESSAGE
                && $context['usuario_id'] === $suppressed->id
                && $context['motivo'] === self::REASON
                && $context['teto_por_janela'] === CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW)
            ->once();

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW);
    }

    /**
     * Teto cheio cala o aviso do momento, não o resto da hora da conta: a janela da conta é
     * devolvida, então a falha seguinte dela chega ao suporte assim que o teto libera. Era aqui que
     * uma falha real de CRLV-e sumia — a conta suprimida ficava calada com crédito global de sobra.
     */
    public function test_account_suppressed_by_the_global_cap_alerts_when_the_cap_frees(): void
    {
        Mail::fake();
        Exceptions::fake();

        $this->exhaustGlobalCap();

        // A conta tropeça no layout novo com o teto já cheio: nenhum e-mail sai.
        $this->travel(50)->minutes();

        $late = User::factory()->asUser()->create();
        $this->reportFailure($late);

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW);

        // Vencida a janela global (60 minutos do primeiro aviso), a janela da conta ainda teria 49
        // minutos de silêncio pela frente: a falha nova dela não pode se perder.
        $this->travel(11)->minutes();

        $this->reportFailure($late);

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW + 1);
        Exceptions::assertReportedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW + 1);
    }

    /**
     * A ordem das duas portas importa: a janela da conta é consultada primeiro, então quem reenvia
     * o mesmo PDF em loop gasta um crédito global, não um por tentativa — senão uma conta sozinha
     * fecharia o aviso de todas as outras.
     */
    public function test_repeat_inside_the_account_window_does_not_spend_global_credits(): void
    {
        Mail::fake();
        Exceptions::fake();

        $noisy = User::factory()->asUser()->create();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->reportFailure($noisy);
        }

        for ($account = 1; $account < CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW; $account++) {
            $this->reportFailure(User::factory()->asUser()->create());
        }

        Mail::assertQueuedCount(CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW);
    }

    /**
     * Gasta os créditos da janela global com a mesma falha em contas diferentes.
     */
    private function exhaustGlobalCap(): void
    {
        for ($account = 1; $account <= CrlvImportFailureReporter::GLOBAL_ALERTS_PER_WINDOW; $account++) {
            $this->reportFailure(User::factory()->asUser()->create());
        }
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
