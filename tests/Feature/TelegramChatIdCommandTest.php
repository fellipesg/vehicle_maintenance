<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramChatIdCommandTest extends TestCase
{
    public function test_lists_distinct_chats_without_printing_the_token(): void
    {
        config(['services.telegram.bot_token' => '123:SECRET']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 1, 'message' => ['chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Alertas RevisaLog']]],
            ['update_id' => 2, 'message' => ['chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Alertas RevisaLog']]],
            ['update_id' => 3, 'message' => ['chat' => ['id' => 555, 'type' => 'private', 'username' => 'felipe']]],
        ]])]);

        $exitCode = Artisan::call('telegram:chat-ids');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('-100123', $output);
        $this->assertStringContainsString('Alertas RevisaLog', $output);
        $this->assertStringContainsString('felipe', $output);
        $this->assertSame(1, substr_count($output, '-100123'));
        $this->assertStringNotContainsString('SECRET', $output);
    }

    public function test_missing_token_fails(): void
    {
        config(['services.telegram.bot_token' => null]);
        Http::fake();

        $this->artisan('telegram:chat-ids')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_telegram_error_fails_without_leaking_the_token(): void
    {
        config(['services.telegram.bot_token' => '123:SECRET']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

        $this->artisan('telegram:chat-ids')
            ->doesntExpectOutputToContain('SECRET')
            ->assertFailed();
    }
}
