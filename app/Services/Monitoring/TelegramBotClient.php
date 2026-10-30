<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramBotClient
{
    private const TIMEOUT_SECONDS = 10;

    public function sendMessage(string $chatId, string $html): void
    {
        Http::timeout(self::TIMEOUT_SECONDS)
            ->asForm()
            ->post($this->url('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ])
            ->throw();
    }

    public function sendDocument(string $chatId, string $filename, string $contents, ?string $caption = null): void
    {
        $fields = ['chat_id' => $chatId];

        if ($caption !== null && $caption !== '') {
            $fields['caption'] = Str::limit($caption, 1000);
        }

        Http::timeout(self::TIMEOUT_SECONDS)
            ->attach('document', $contents, $filename)
            ->post($this->url('sendDocument'), $fields)
            ->throw();
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
    }
}
