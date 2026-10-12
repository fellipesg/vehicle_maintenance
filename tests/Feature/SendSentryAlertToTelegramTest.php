<?php

namespace Tests\Feature;

use App\Jobs\SendSentryAlertToTelegram;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendSentryAlertToTelegramTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'event' => [
                'event_id' => 'ev1',
                'issue_id' => '77',
                'title' => 'RuntimeException: boom',
                'web_url' => 'https://sentry.io/e/1',
                'exception' => ['values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'boom',
                    'stacktrace' => ['frames' => [
                        ['filename' => '/app/Foo.php', 'function' => 'bar', 'lineno' => 3, 'in_app' => true],
                    ]],
                ]]],
            ],
            'triggered_rule' => 'Todos os erros',
        ];
    }

    private function configure(): void
    {
        config([
            'services.telegram.bot_token' => '123:ABC',
            'services.telegram.alerts_chat_id' => '-100999',
        ]);
    }

    public function test_sends_message_and_stack_trace_document(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        SendSentryAlertToTelegram::dispatchSync($this->payload());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123:ABC/sendMessage'
            && $request['chat_id'] === '-100999'
            && $request['parse_mode'] === 'HTML'
            && str_contains($request['text'], 'RuntimeException: boom'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123:ABC/sendDocument'
            && str_contains($request->body(), 'stacktrace-77.txt')
            && str_contains($request->body(), '-100999')
            && str_contains($request->body(), '[app]'));
    }

    public function test_skips_document_when_there_is_no_stack_trace(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        SendSentryAlertToTelegram::dispatchSync(['event' => ['title' => 'Só mensagem']]);

        Http::assertSentCount(1);
    }

    public function test_missing_configuration_makes_no_http_calls(): void
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.alerts_chat_id' => '-100999']);
        Http::fake();

        SendSentryAlertToTelegram::dispatchSync($this->payload());

        config(['services.telegram.bot_token' => '123:ABC', 'services.telegram.alerts_chat_id' => null]);
        SendSentryAlertToTelegram::dispatchSync($this->payload());

        Http::assertNothingSent();
    }

    public function test_telegram_failure_is_logged_but_never_reported(): void
    {
        $this->configure();
        Exceptions::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        Log::shouldReceive('channel')->with('single')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), '123:ABC'));

        SendSentryAlertToTelegram::dispatchSync($this->payload());

        Exceptions::assertNothingReported();
    }
}
