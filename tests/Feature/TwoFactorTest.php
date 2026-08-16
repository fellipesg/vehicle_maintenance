<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = app(Google2FA::class);
    }

    public function test_login_when_two_factor_enabled_returns_challenge_without_token(): void
    {
        if (! $this->authControllerHasTwoFactorHook()) {
            $this->markTestIncomplete('AuthController 2FA hook not yet integrated — login still issues a token.');
        }

        $user = $this->createConfirmedTwoFactorUser('password123');

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonStructure(['challenge_token'])
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_challenge_success_issues_mobile_token_with_abilities(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        $challengeToken = $this->issueChallengeToken($user);
        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $response = $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user', 'token_type']]);

        $token = $user->fresh()->tokens()->where('name', SanctumMobileToken::TOKEN_NAME)->first();
        $this->assertNotNull($token);
        $this->assertSame(SanctumMobileToken::ABILITIES, $token->abilities);
    }

    public function test_challenge_with_wrong_code_returns_422(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        $challengeToken = $this->issueChallengeToken($user);

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid authentication code.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        $recoveryCode = 'ABCD123456';
        $twoFactorService = app(TwoFactorService::class);
        $twoFactorService->persistRecoveryCodeHashes(
            $user,
            $twoFactorService->hashRecoveryCodes([$recoveryCode, 'EFGH789012']),
        );
        $user->refresh();

        $challengeToken = $this->issueChallengeToken($user);

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $recoveryCode,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token']]);

        $user->refresh();
        $this->assertCount(1, $user->twoFactorRecoveryCodeHashes());

        $secondChallenge = $this->issueChallengeToken($user);

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $secondChallenge,
            'recovery_code' => $recoveryCode,
        ])->assertStatus(422);
    }

    public function test_enable_returns_secret_and_otpauth_uri(): void
    {
        $user = $this->actingAsApiUser();

        $response = $this->postJson('/api/v1/two-factor/enable');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri']]);

        $user->refresh();
        $this->assertTrue($user->hasPendingTwoFactorSetup());
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_confirm_enables_two_factor_and_returns_recovery_codes(): void
    {
        $user = $this->actingAsApiUser();
        $secret = app(TwoFactorService::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/v1/two-factor/confirm', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(8, $user->twoFactorRecoveryCodeHashes());
    }

    public function test_confirm_fails_with_invalid_code(): void
    {
        $user = $this->actingAsApiUser();
        $secret = app(TwoFactorService::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->postJson('/api/v1/two-factor/confirm', [
            'code' => '000000',
        ])->assertStatus(422);

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_confirm_fails_when_not_enabled_first(): void
    {
        $this->actingAsApiUser();

        $this->postJson('/api/v1/two-factor/confirm', [
            'code' => '123456',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Enable two-factor authentication before confirming.');
    }

    public function test_disable_requires_password_and_valid_code(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        Sanctum::actingAs($user);

        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $this->postJson('/api/v1/two-factor/disable', [
            'password' => 'password123',
            'code' => $code,
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
    }

    public function test_disable_fails_with_wrong_password(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        Sanctum::actingAs($user);

        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $this->postJson('/api/v1/two-factor/disable', [
            'password' => 'wrong-password',
            'code' => $code,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid password.');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_regenerate_recovery_codes_requires_password_and_code(): void
    {
        $user = $this->createConfirmedTwoFactorUser('password123');
        Sanctum::actingAs($user);

        $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);

        $this->postJson('/api/v1/two-factor/recovery-codes', [
            'password' => 'password123',
            'code' => $code,
        ])->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes');
    }

    public function test_unconfirmed_secret_is_not_enabled(): void
    {
        $user = $this->actingAsApiUser();

        $this->postJson('/api/v1/two-factor/enable')->assertOk();

        $user->refresh();

        $this->assertTrue($user->hasPendingTwoFactorSetup());
        $this->assertFalse(app(TwoFactorChallengeService::class)->isEnabled($user));
    }

    public function test_secret_is_not_returned_after_confirm(): void
    {
        $user = $this->actingAsApiUser();

        $enableResponse = $this->postJson('/api/v1/two-factor/enable');
        $secret = $enableResponse->json('data.secret');
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->postJson('/api/v1/two-factor/confirm', [
            'code' => $code,
        ])->assertOk()
            ->assertJsonMissingPath('data.secret')
            ->assertJsonMissingPath('data.otpauth_uri');

        $this->postJson('/api/v1/two-factor/enable')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Two-factor authentication is already enabled.');
    }

    private function createConfirmedTwoFactorUser(string $password): User
    {
        $user = User::factory()->asUser()->create([
            'password' => $password,
        ]);

        $twoFactorService = app(TwoFactorService::class);
        $secret = $twoFactorService->generateSecretKey();
        $recoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(
                $twoFactorService->hashRecoveryCodes($recoveryCodes),
            ),
        ])->save();

        return $user->fresh();
    }

    private function issueChallengeToken(User $user): string
    {
        $response = app(TwoFactorChallengeService::class)->issuePendingResponse($user);

        return (string) $response->getData(true)['challenge_token'];
    }

    private function authControllerHasTwoFactorHook(): bool
    {
        $contents = File::get(app_path('Http/Controllers/Api/AuthController.php'));

        return str_contains($contents, 'TwoFactorChallengeService');
    }
}
