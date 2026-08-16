<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const MOBILE_TOKEN_ABILITIES = [
        'profile:read',
        'profile:write',
        'vehicles:read',
        'vehicles:write',
        'maintenances:read',
        'maintenances:write',
        'invoices:write',
        'workshops:read',
        'workshops:write',
        'fcm:write',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth');
        RateLimiter::clear('auth-web');
    }

    public function test_can_register_user_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'user',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'joao@example.com');

        $user = User::where('email', 'joao@example.com')->first();
        $this->assertNotNull($user->tenant_id);
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'type' => 'individual',
        ]);
    }

    public function test_can_register_garage_with_tenant(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Garagem Central',
            'email' => 'garagem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'garage',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_type']);

        $this->assertDatabaseMissing('users', ['email' => 'garagem@example.com']);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('garages', 0);
    }

    public function test_public_api_register_with_workshop_is_rejected(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Oficina Central',
            'email' => 'oficina@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'workshop',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_type']);

        $this->assertDatabaseMissing('users', ['email' => 'oficina@example.com']);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('workshops', 0);
    }

    public function test_can_login(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_can_login_via_matching_portal(): void
    {
        User::factory()->asUser()->create([
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
            'portal' => 'usuario',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'owner@example.com');
    }

    public function test_login_rejects_wrong_portal(): void
    {
        User::factory()->asUser()->create([
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
            'portal' => 'lojista',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Esta conta não tem acesso a este portal.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_can_login_via_admin_portal(): void
    {
        User::factory()->asUser()->asAdmin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'portal' => 'admin',
        ])->assertOk();
    }

    public function test_can_get_authenticated_user(): void
    {
        $user = $this->actingAsApiUser();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.tenant_id', $user->tenant_id);
    }

    public function test_can_logout(): void
    {
        $user = $this->actingAsApiUser();

        $this->postJson('/api/v1/logout')
            ->assertOk();
    }

    public function test_second_login_revokes_previous_mobile_token(): void
    {
        User::factory()->asUser()->create([
            'email' => 'revoke@example.com',
            'password' => bcrypt('password123'),
        ]);

        $firstToken = $this->postJson('/api/v1/login', [
            'email' => 'revoke@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $secondToken = $this->postJson('/api/v1/login', [
            'email' => 'revoke@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_issued_token_has_expected_abilities(): void
    {
        User::factory()->asUser()->create([
            'email' => 'abilities@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'abilities@example.com',
            'password' => 'password123',
        ])->assertOk();

        $user = User::where('email', 'abilities@example.com')->firstOrFail();
        $token = $user->tokens()->where('name', 'mobile')->first();

        $this->assertNotNull($token);
        $this->assertEqualsCanonicalizing(self::MOBILE_TOKEN_ABILITIES, $token->abilities);
        $this->assertNotNull($token->expires_at);
    }

    public function test_mobile_token_expires_in_thirty_days(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        User::factory()->asUser()->create([
            'email' => 'expiry@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'expiry@example.com',
            'password' => 'password123',
        ])->assertOk();

        $user = User::where('email', 'expiry@example.com')->firstOrFail();
        $token = $user->tokens()->where('name', SanctumMobileToken::TOKEN_NAME)->firstOrFail();

        $this->assertTrue($token->expires_at->equalTo(Carbon::parse('2026-01-31 12:00:00')));

        Carbon::setTestNow();
    }

    public function test_expired_token_is_rejected(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        User::factory()->asUser()->create([
            'email' => 'expired@example.com',
            'password' => bcrypt('password123'),
        ]);

        $plainToken = $this->postJson('/api/v1/login', [
            'email' => 'expired@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        Carbon::setTestNow('2026-02-01 12:00:00');

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        Carbon::setTestNow();
    }

    public function test_sixth_login_in_a_minute_returns_429(): void
    {
        User::factory()->asUser()->create([
            'email' => 'ratelimit@example.com',
            'password' => bcrypt('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_sixth_register_in_a_minute_returns_429(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/register', [
                'name' => 'User '.$attempt,
                'email' => "register-limit-{$attempt}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'mismatch',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/register', [
            'name' => 'Blocked User',
            'email' => 'register-limit-blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_oauth_callback_rate_limit_is_segmented_by_provider(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->getJson('/api/v1/auth/google/callback');
            $this->assertNotSame(429, $response->status());
        }

        $response = $this->getJson('/api/v1/auth/facebook/callback');
        $this->assertNotSame(429, $response->status());

        $this->getJson('/api/v1/auth/google/callback')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many attempts. Please try again later.');
    }

    public function test_oauth_callback_rate_limit_does_not_block_login_bucket(): void
    {
        User::factory()->asUser()->create([
            'email' => 'oauth-bucket@example.com',
            'password' => bcrypt('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->getJson('/api/v1/auth/google/callback');
        }

        $this->postJson('/api/v1/login', [
            'email' => 'oauth-bucket@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_rate_limit_is_segmented_by_email(): void
    {
        User::factory()->asUser()->create([
            'email' => 'first@example.com',
            'password' => bcrypt('password123'),
        ]);

        User::factory()->asUser()->create([
            'email' => 'second@example.com',
            'password' => bcrypt('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'first@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'second@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_api_login_does_not_persist_web_session(): void
    {
        User::factory()->asUser()->create([
            'email' => 'sessionless@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'sessionless@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertGuest('web');
    }
}
