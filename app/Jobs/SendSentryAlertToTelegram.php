<?php

namespace App\Jobs;

use App\Services\Monitoring\SentryTelegramAlertFormatter;
use App\Services\Monitoring\TelegramBotClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SendSentryAlertToTelegram
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  O `data` do webhook event_alert do Sentry.
     */
    public function __construct(public array $payload) {}

    /**
     * Falhas do Telegram nunca são reportadas ao Sentry (report() ou rethrow): um erro aqui geraria
     * outro alerta, outro webhook e um loop. Só vão para o log local.
     */
    public function handle(SentryTelegramAlertFormatter $formatter, TelegramBotClient $telegram): void
    {
        $token = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.alerts_chat_id');

        if ($token === '' || $chatId === '') {
            return;
        }

        try {
            $alert = $formatter->format($this->payload);

            $telegram->sendMessage($chatId, $alert['message']);

            if ($alert['fullTrace'] !== null) {
                $telegram->sendDocument($chatId, $alert['filename'], $alert['fullTrace'], 'Stack trace completo');
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Falha ao enviar alerta do Sentry ao Telegram.', [
                'exception' => $e::class,
                'message' => str_replace($token, '[token]', $e->getMessage()),
            ]);
        }
    }
}
