<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TelegramChatIdCommand extends Command
{
    protected $signature = 'telegram:chat-ids';

    protected $description = 'Lista os chats que falaram com o bot (getUpdates) para descobrir o TELEGRAM_ALERTS_CHAT_ID';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN não está configurado.');

            return self::FAILURE;
        }

        try {
            $updates = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getUpdates")
                ->throw()
                ->json('result', []);
        } catch (RequestException $e) {
            $this->error('O Telegram recusou a consulta (HTTP '.$e->response->status().'). Confira o token do bot.');

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('Não foi possível falar com o Telegram.');

            return self::FAILURE;
        }

        $chats = [];

        foreach ((array) $updates as $update) {
            foreach (['message', 'edited_message', 'channel_post', 'my_chat_member'] as $key) {
                $chat = data_get($update, "{$key}.chat");

                if (is_array($chat) && isset($chat['id'])) {
                    $chats[(string) $chat['id']] = [
                        (string) $chat['id'],
                        (string) ($chat['type'] ?? ''),
                        (string) ($chat['title'] ?? $chat['username'] ?? trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? ''))),
                    ];
                }
            }
        }

        if ($chats === []) {
            $this->warn('Nenhum chat encontrado. Adicione o bot ao grupo e envie uma mensagem nele; depois rode de novo.');

            return self::SUCCESS;
        }

        $this->table(['chat_id', 'tipo', 'título/usuário'], array_values($chats));

        return self::SUCCESS;
    }
}
