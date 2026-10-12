<?php

namespace Tests\Feature;

use App\Jobs\SendSentryAlertToTelegram;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SentryWebhookControllerTest extends TestCase
{
    private const SECRET = 'segredo-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sentry_webhook.client_secret' => self::SECRET]);
        Bus::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'action' => 'triggered',
            'data' => [
                'event' => ['title' => 'RuntimeException: boom', 'event_id' => 'abc123'],
                'triggered_rule' => 'Todos os erros',
            ],
            'installation' => ['uuid' => 'x'],
            'actor' => ['type' => 'application', 'id' => 'sentry'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function headers(array $payload, string $resource = 'event_alert', ?string $secret = null): array
    {
        return [
            'Sentry-Hook-Resource' => $resource,
            'Sentry-Hook-Signature' => hash_hmac('sha256', json_encode($payload), $secret ?? self::SECRET),
            'Sentry-Hook-Timestamp' => (string) time(),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function postWebhook(array $payload, array $headers)
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $server['CONTENT_TYPE'] = 'application/json';

        return $this->call('POST', route('webhooks.sentry'), [], [], [], $server, json_encode($payload));
    }

    public function test_valid_event_alert_dispatches_job_after_response(): void
    {
        $payload = $this->payload();

        $this->postWebhook($payload, $this->headers($payload))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Bus::assertDispatchedAfterResponse(
            SendSentryAlertToTelegram::class,
            fn (SendSentryAlertToTelegram $job): bool => $job->payload === $payload['data'],
        );
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->payload();

        $this->postWebhook($payload, $this->headers($payload, secret: 'outro-segredo'))->assertUnauthorized();

        Bus::assertNothingDispatched();
    }

    public function test_missing_signature_is_rejected(): void
    {
        $payload = $this->payload();
        $headers = $this->headers($payload);
        unset($headers['Sentry-Hook-Signature']);

        $this->postWebhook($payload, $headers)->assertUnauthorized();

        Bus::assertNothingDispatched();
    }

    public function test_unconfigured_secret_returns_service_unavailable(): void
    {
        config(['services.sentry_webhook.client_secret' => null]);
        $payload = $this->payload();

        $this->postWebhook($payload, $this->headers($payload, secret: ''))->assertStatus(503);

        Bus::assertNothingDispatched();
    }

    public function test_other_resources_are_acknowledged_and_ignored(): void
    {
        $payload = ['action' => 'created', 'data' => ['installation' => []]];

        $this->postWebhook($payload, $this->headers($payload, 'installation'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Bus::assertNothingDispatched();
    }
}
