<?php

namespace Tests\Feature;

use App\Services\Monitoring\SentryTelegramAlertFormatter;
use Tests\TestCase;

class SentryTelegramAlertFormatterTest extends TestCase
{
    /**
     * @param  array<int, array<string, mixed>>  $frames
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function exceptionData(array $frames, string $value = 'Falha ao salvar', array $overrides = []): array
    {
        return [
            'event' => array_merge([
                'event_id' => 'ev1',
                'issue_id' => '4242',
                'title' => 'RuntimeException: '.$value,
                'culprit' => 'App\Http\Controllers\Foo::bar',
                'level' => 'error',
                'environment' => 'production',
                'release' => 'abc1234',
                'web_url' => 'https://sentry.io/organizations/x/issues/4242/events/ev1/',
                'request' => ['url' => 'https://revisalog.com.br/garagem', 'method' => 'POST'],
                'exception' => ['values' => [[
                    'type' => 'RuntimeException',
                    'value' => $value,
                    'stacktrace' => ['frames' => $frames],
                ]]],
            ], $overrides),
            'triggered_rule' => 'Todos os erros',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function frame(string $file, int $line, string $function, bool $inApp): array
    {
        return ['filename' => $file, 'abs_path' => '/var/www/html'.$file, 'function' => $function, 'lineno' => $line, 'in_app' => $inApp];
    }

    public function test_message_has_only_app_frames_newest_first_and_escapes_html(): void
    {
        $data = $this->exceptionData([
            $this->frame('/vendor/laravel/framework/src/Pipeline.php', 10, 'handle', false),
            $this->frame('/app/Http/Controllers/Foo.php', 30, 'store', true),
            $this->frame('/app/Services/Bar.php', 55, 'run', true),
        ], 'valor <script>alert(1)</script>');

        $result = (new SentryTelegramAlertFormatter)->format($data);
        $message = $result['message'];

        $this->assertStringContainsString('🚨 <b>RuntimeException: valor &lt;script&gt;alert(1)&lt;/script&gt;</b>', $message);
        $this->assertStringNotContainsString('<script>', $message);
        $this->assertStringContainsString('<b>Ambiente:</b> production', $message);
        $this->assertStringContainsString('<b>Nível:</b> error', $message);
        $this->assertStringContainsString('<b>Release:</b> abc1234', $message);
        $this->assertStringContainsString('<b>Requisição:</b> POST https://revisalog.com.br/garagem', $message);
        $this->assertStringNotContainsString('Pipeline.php', $message);
        $this->assertLessThan(
            strpos($message, 'app/Http/Controllers/Foo.php:30 in store'),
            strpos($message, 'app/Services/Bar.php:55 in run'),
        );
        $this->assertStringContainsString('<a href="https://sentry.io/organizations/x/issues/4242/events/ev1/">Abrir no Sentry</a>', $message);
        $this->assertStringContainsString('Regra: Todos os erros', $message);
        $this->assertSame('stacktrace-4242.txt', $result['filename']);
    }

    public function test_message_only_event_without_exception(): void
    {
        $result = (new SentryTelegramAlertFormatter)->format([
            'event' => [
                'title' => 'Algo deu errado',
                'logentry' => ['formatted' => 'Pagamento <falhou>'],
                'level' => 'warning',
                'event_id' => 'e9',
            ],
        ]);

        $this->assertStringContainsString('<b>Pagamento &lt;falhou&gt;</b>', $result['message']);
        $this->assertStringNotContainsString('<pre>', $result['message']);
        $this->assertNull($result['fullTrace']);
        $this->assertSame('stacktrace-e9.txt', $result['filename']);
    }

    public function test_empty_payload_does_not_break(): void
    {
        $result = (new SentryTelegramAlertFormatter)->format([]);

        $this->assertStringContainsString('Erro sem título', $result['message']);
        $this->assertNull($result['fullTrace']);
    }

    public function test_more_than_fifteen_frames_shows_remaining_count(): void
    {
        $frames = [];
        for ($i = 1; $i <= 20; $i++) {
            $frames[] = $this->frame("/app/File{$i}.php", $i, "fn{$i}", true);
        }

        $message = (new SentryTelegramAlertFormatter)->format($this->exceptionData($frames))['message'];

        $this->assertStringContainsString('… +5 frames', $message);
        $this->assertStringContainsString('app/File20.php:20', $message);
        $this->assertStringContainsString('app/File6.php:6', $message);
        $this->assertStringNotContainsString('app/File5.php:5 ', $message);
    }

    public function test_without_app_frames_shows_top_five_frames(): void
    {
        $frames = [];
        for ($i = 1; $i <= 8; $i++) {
            $frames[] = $this->frame("/vendor/pkg/V{$i}.php", $i, "v{$i}", false);
        }

        $message = (new SentryTelegramAlertFormatter)->format($this->exceptionData($frames))['message'];

        $this->assertStringContainsString('vendor/pkg/V8.php:8', $message);
        $this->assertStringContainsString('vendor/pkg/V4.php:4', $message);
        $this->assertStringNotContainsString('vendor/pkg/V3.php', $message);
        $this->assertStringContainsString('… +3 frames', $message);
    }

    public function test_huge_frames_keep_message_within_telegram_limit(): void
    {
        $frames = [];
        for ($i = 1; $i <= 15; $i++) {
            $frames[] = $this->frame('/app/'.str_repeat("Deep{$i}/", 40).'File.php', $i, str_repeat('método<', 30), true);
        }

        $message = (new SentryTelegramAlertFormatter)->format($this->exceptionData($frames))['message'];

        $this->assertLessThanOrEqual(4096, mb_strlen($message));
        $this->assertStringContainsString('<pre>', $message);
        $this->assertStringContainsString('</pre>', $message);
        $this->assertStringContainsString('Abrir no Sentry</a>', $message);
        $this->assertMatchesRegularExpression('/… \+\d+ frames/', $message);
    }

    public function test_full_trace_contains_vendor_frames_and_chained_exceptions(): void
    {
        $data = $this->exceptionData([
            $this->frame('/vendor/laravel/framework/src/Pipeline.php', 10, 'handle', false),
            $this->frame('/app/Http/Controllers/Foo.php', 30, 'store', true),
        ]);
        array_unshift($data['event']['exception']['values'], [
            'type' => 'PDOException',
            'value' => 'SQLSTATE[23000]',
            'stacktrace' => ['frames' => [
                $this->frame('/vendor/laravel/framework/src/Connection.php', 99, 'run', false),
            ]],
        ]);

        $trace = (new SentryTelegramAlertFormatter)->format($data)['fullTrace'];

        $this->assertNotNull($trace);
        $this->assertStringContainsString('RuntimeException: Falha ao salvar', $trace);
        $this->assertStringContainsString('PDOException: SQLSTATE[23000]', $trace);
        $this->assertStringContainsString('[vendor] vendor/laravel/framework/src/Pipeline.php:10 in handle', $trace);
        $this->assertStringContainsString('[app]    app/Http/Controllers/Foo.php:30 in store', $trace);
        $this->assertStringContainsString('[vendor] vendor/laravel/framework/src/Connection.php:99 in run', $trace);
        $this->assertLessThan(
            strpos($trace, 'Pipeline.php'),
            strpos($trace, 'app/Http/Controllers/Foo.php'),
        );
    }
}
