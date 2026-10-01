<?php

namespace Tests\Feature;

use App\Enums\RegistrationSource;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    /** @var array<string, string> */
    private array $jwk;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.apple.client_id' => 'br.com.revisalog.app']);
        Cache::flush();
        RateLimiter::clear('oauth:apple|127.0.0.1');
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $details = openssl_pkey_get_details($key);
        openssl_pkey_export($key, $privateKey);

        $this->privateKey = $privateKey;
        $this->jwk = [
            'kty' => 'RSA',
            'kid' => 'test-key',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
    }

    public function test_new_apple_user_can_sign_in_with_a_private_relay_email(): void
    {
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $token = $this->signToken($this->claims($rawNonce, [
            'email' => 'relay.user@privaterelay.appleid.com',
            'email_verified' => 'true',
        ]));

        $response = $this->postApple($token, $rawNonce, ['name' => 'Ana Silva']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'relay.user@privaterelay.appleid.com')
            ->assertJsonPath('data.token_type', 'Bearer');
        $this->assertNotEmpty($response->json('data.token'));

        $user = User::query()->where('email', 'relay.user@privaterelay.appleid.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('apple', $user->provider);
        $this->assertSame('apple-user-1', $user->provider_id);
        $this->assertSame('Ana Silva', $user->name);
        $this->assertSame('user', $user->user_type);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->tenant_id);
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'type' => 'individual',
        ]);

        Mail::assertQueued(WelcomeUserMail::class, function (WelcomeUserMail $mail) use ($user): bool {
            return $mail->hasTo($user->email) && $mail->source === RegistrationSource::Oauth;
        });
    }

    public function test_returning_apple_user_can_sign_in_without_an_email_claim(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'relay.user@privaterelay.appleid.com',
            'provider' => 'apple',
            'provider_id' => 'apple-user-1',
        ]);
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $claims = $this->claims($rawNonce);
        unset($claims['email'], $claims['email_verified']);
        $token = $this->signToken($claims);

        $response = $this->postApple($token, $rawNonce);

        $response->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
        Mail::assertNothingQueued();
    }

    public function test_apple_sign_in_links_an_existing_account_with_the_same_email(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'ana@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $token = $this->signToken($this->claims($rawNonce, [
            'email' => 'ana@example.com',
            'sub' => 'apple-linked',
        ]));

        $this->postApple($token, $rawNonce)->assertOk();

        $user->refresh();
        $this->assertSame('apple', $user->provider);
        $this->assertSame('apple-linked', $user->provider_id);
    }

    public function test_lojista_apple_sign_in_creates_a_garage_account(): void
    {
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $token = $this->signToken($this->claims($rawNonce, [
            'email' => 'loja@privaterelay.appleid.com',
            'sub' => 'apple-garage',
        ]));

        $this->postApple($token, $rawNonce, [
            'portal' => 'lojista',
            'name' => 'Loja Ana',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'loja@privaterelay.appleid.com');

        $user = User::query()->where('email', 'loja@privaterelay.appleid.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('garage', $user->user_type);
        $this->assertDatabaseHas('garages', [
            'user_id' => $user->id,
            'email' => 'loja@privaterelay.appleid.com',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'type' => 'garage',
        ]);
    }

    public function test_apple_sign_in_rejects_a_portal_the_account_cannot_open(): void
    {
        $user = User::factory()->asWorkshop()->create([
            'email' => 'oficina@example.com',
        ]);
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $token = $this->signToken($this->claims($rawNonce, [
            'email' => 'oficina@example.com',
        ]));

        $this->postApple($token, $rawNonce)
            ->assertForbidden()
            ->assertJsonPath('message', 'This account does not have access to this portal.');

        $user->refresh();
        $this->assertNull($user->provider);
        $this->assertNull($user->provider_id);
    }

    public function test_apple_sign_in_does_not_create_an_admin_account(): void
    {
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $token = $this->signToken($this->claims($rawNonce, [
            'email' => 'admin-try@privaterelay.appleid.com',
        ]));

        $this->postApple($token, $rawNonce, ['portal' => 'admin'])->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'admin-try@privaterelay.appleid.com',
        ]);
    }

    public function test_first_apple_sign_in_requires_an_email(): void
    {
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';
        $claims = $this->claims($rawNonce);
        unset($claims['email'], $claims['email_verified']);

        $this->postApple($this->signToken($claims), $rawNonce)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Apple did not share an email address.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_apple_sign_in_rejects_an_invalid_signature(): void
    {
        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $details = openssl_pkey_get_details($other);
        $this->fakeAppleKeys([
            'kty' => 'RSA',
            'kid' => 'test-key',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]);
        $rawNonce = 'raw-nonce-value';

        $this->postApple($this->signToken($this->claims($rawNonce)), $rawNonce)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unable to authenticate with Apple.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_apple_sign_in_rejects_expired_wrong_audience_and_nonce_mismatch(): void
    {
        $this->fakeAppleKeys($this->jwk);
        $rawNonce = 'raw-nonce-value';

        $this->postApple(
            $this->signToken($this->claims($rawNonce, ['exp' => now()->subHour()->getTimestamp()])),
            $rawNonce,
        )->assertUnauthorized();

        $this->postApple(
            $this->signToken($this->claims($rawNonce, ['aud' => 'com.example.other'])),
            $rawNonce,
        )->assertUnauthorized();

        $this->postApple(
            $this->signToken($this->claims($rawNonce)),
            'different-nonce',
        )->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_apple_sign_in_refreshes_apple_keys_when_the_key_id_is_unknown(): void
    {
        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::sequence()
                ->push(['keys' => [[
                    'kty' => 'RSA',
                    'kid' => 'old-key',
                    'n' => $this->jwk['n'],
                    'e' => $this->jwk['e'],
                ]]])
                ->push(['keys' => [$this->jwk]]),
        ]);
        $rawNonce = 'raw-nonce-value';

        $this->postApple(
            $this->signToken($this->claims($rawNonce, [
                'email' => 'rotated@privaterelay.appleid.com',
                'sub' => 'apple-rotated',
            ])),
            $rawNonce,
        )->assertOk()
            ->assertJsonPath('data.user.email', 'rotated@privaterelay.appleid.com');
    }

    public function test_apple_sign_in_rate_limit_does_not_block_password_login(): void
    {
        User::factory()->asUser()->create([
            'email' => 'owner@example.com',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/apple', [
                'identity_token' => 'not-a-jwt',
                'nonce' => 'raw-nonce-value',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
            'portal' => 'usuario',
        ])->assertOk();
    }

    public function test_apple_sign_in_validates_the_payload(): void
    {
        $this->postJson('/api/v1/auth/apple', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identity_token', 'nonce']);
    }

    /**
     * @param  array<string, string>  $jwk
     */
    private function fakeAppleKeys(array $jwk): void
    {
        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response(['keys' => [$jwk]]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function postApple(string $token, string $rawNonce, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/apple', array_merge([
            'identity_token' => $token,
            'nonce' => $rawNonce,
            'portal' => 'usuario',
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function claims(string $rawNonce, array $extra = []): array
    {
        return array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'br.com.revisalog.app',
            'exp' => now()->addHour()->getTimestamp(),
            'iat' => now()->getTimestamp(),
            'sub' => 'apple-user-1',
            'email' => 'relay.user@privaterelay.appleid.com',
            'email_verified' => 'true',
            'nonce' => hash('sha256', $rawNonce),
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signToken(array $payload, ?string $privateKey = null): string
    {
        $header = $this->base64Url((string) json_encode([
            'alg' => 'RS256',
            'kid' => 'test-key',
        ], JSON_THROW_ON_ERROR));
        $body = $this->base64Url((string) json_encode($payload, JSON_THROW_ON_ERROR));
        openssl_sign(
            $header.'.'.$body,
            $signature,
            $privateKey ?? $this->privateKey,
            OPENSSL_ALGO_SHA256,
        );

        return $header.'.'.$body.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
