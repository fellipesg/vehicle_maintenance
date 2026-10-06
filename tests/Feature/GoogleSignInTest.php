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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const AUDIENCE = 'web-client.apps.googleusercontent.com';

    private string $privateKey;

    /** @var array<string, string> */
    private array $jwk;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.token_audiences' => [self::AUDIENCE]]);
        Cache::flush();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($key);
        openssl_pkey_export($key, $privateKey);

        $this->privateKey = $privateKey;
        $this->jwk = [
            'kty' => 'RSA', 'kid' => 'google-key', 'use' => 'sig', 'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$this->jwk]])]);
    }

    public function test_a_new_google_user_signs_in_and_gets_a_token(): void
    {
        $response = $this->postGoogle($this->signToken($this->claims()));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ana@gmail.com')
            ->assertJsonPath('data.token_type', 'Bearer');

        $user = User::query()->where('email', 'ana@gmail.com')->sole();
        $this->assertSame('google', $user->provider);
        $this->assertSame('google-sub-1', $user->provider_id);
        $this->assertSame('Ana Souza', $user->name);
        $this->assertNotNull($user->email_verified_at);
        Mail::assertQueued(WelcomeUserMail::class, fn (WelcomeUserMail $mail): bool => $mail->hasTo('ana@gmail.com') && $mail->source === RegistrationSource::Oauth);
    }

    public function test_an_existing_email_account_is_linked_without_losing_its_photo(): void
    {
        $user = User::factory()->create(['email' => 'ana@gmail.com', 'avatar' => 'avatars/mine.jpg']);

        $this->postGoogle($this->signToken($this->claims()))->assertOk()->assertJsonPath('data.user.id', $user->id);

        $user->refresh();
        $this->assertSame('google', $user->provider);
        $this->assertSame('avatars/mine.jpg', $user->avatar);
        $this->assertSame(1, User::count());
        Mail::assertNotQueued(WelcomeUserMail::class);
    }

    public function test_a_lojista_portal_sign_up_creates_a_garage_user_and_workshop_portal_refuses_new_accounts(): void
    {
        $this->postGoogle($this->signToken($this->claims()), ['portal' => 'lojista'])->assertOk();
        $this->assertSame('garage', User::query()->where('email', 'ana@gmail.com')->value('user_type'));

        $this->postGoogle($this->signToken($this->claims(['sub' => 'other', 'email' => 'novo@gmail.com'])), ['portal' => 'oficina'])
            ->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'novo@gmail.com']);
    }

    public function test_tokens_with_wrong_audience_issuer_expiry_unverified_email_or_signature_are_rejected(): void
    {
        $cases = [
            $this->signToken($this->claims(['aud' => 'someone-else.apps.googleusercontent.com'])),
            $this->signToken($this->claims(['iss' => 'https://evil.example'])),
            $this->signToken($this->claims(['exp' => now()->subHour()->getTimestamp()])),
            $this->signToken($this->claims(['email_verified' => false])),
            $this->tamper($this->signToken($this->claims())),
            'not-a-jwt',
        ];

        foreach ($cases as $token) {
            Cache::flush();
            $this->postGoogle($token)->assertStatus(401)->assertJsonPath('success', false);
        }

        $this->assertSame(0, User::count());
    }

    public function test_the_id_token_is_required(): void
    {
        $this->postJson('/api/v1/auth/google', [])->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function postGoogle(string $token, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/google', ['id_token' => $token, ...$extra]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => self::AUDIENCE,
            'sub' => 'google-sub-1',
            'email' => 'Ana@gmail.com',
            'email_verified' => true,
            'name' => 'Ana Souza',
            'picture' => 'https://lh3.googleusercontent.com/a/photo',
            'iat' => now()->getTimestamp(),
            'exp' => now()->addHour()->getTimestamp(),
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function signToken(array $claims): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'kid' => 'google-key', 'typ' => 'JWT']));
        $payload = $this->base64Url(json_encode($claims));
        openssl_sign($header.'.'.$payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $header.'.'.$payload.'.'.$this->base64Url($signature);
    }

    private function tamper(string $token): string
    {
        [$header, , $signature] = explode('.', $token);

        return $header.'.'.$this->base64Url(json_encode($this->claims(['email' => 'outro@gmail.com']))).'.'.$signature;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
