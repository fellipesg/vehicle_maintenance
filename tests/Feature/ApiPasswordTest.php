<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('password-forgot|owner@test.com|127.0.0.1');
        RateLimiter::clear('password-forgot-ip|127.0.0.1');
    }

    public function test_forgot_sends_the_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->asUser()->create(['email' => 'owner@test.com']);

        $this->postJson('/api/v1/password/forgot', ['email' => 'owner@test.com'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', PasswordResetController::LINK_REQUESTED_MESSAGE);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_answers_the_same_for_an_unknown_email(): void
    {
        Notification::fake();

        // A resposta não pode revelar quais e-mails estão cadastrados.
        $this->postJson('/api/v1/password/forgot', ['email' => 'quemnaoexiste@test.com'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', PasswordResetController::LINK_REQUESTED_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_forgot_requires_a_valid_email(): void
    {
        $this->postJson('/api/v1/password/forgot', ['email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email', 'errors');
    }

    public function test_update_changes_the_password(): void
    {
        $user = User::factory()->asUser()->create([
            'password' => Hash::make('senha-antiga-123'),
        ]);
        $this->actingAsApiUser($user);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'senha-antiga-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('senha-nova-456', $user->fresh()->password));
    }

    public function test_update_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->asUser()->create([
            'password' => Hash::make('senha-antiga-123'),
        ]);
        $this->actingAsApiUser($user);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'chute-errado',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password', 'errors');

        $this->assertTrue(Hash::check('senha-antiga-123', $user->fresh()->password));
    }

    public function test_update_requires_a_confirmation_and_a_different_password(): void
    {
        $user = User::factory()->asUser()->create([
            'password' => Hash::make('senha-antiga-123'),
        ]);
        $this->actingAsApiUser($user);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'senha-antiga-123',
            'password' => 'senha-antiga-123',
            'password_confirmation' => 'senha-antiga-123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password', 'errors');
    }

    public function test_update_signs_out_the_other_devices_but_not_this_one(): void
    {
        $user = User::factory()->asUser()->create([
            'password' => Hash::make('senha-antiga-123'),
        ]);

        // Token de outro aparelho, que deve cair.
        $otherDevice = $user->createToken('other-device', SanctumMobileToken::ABILITIES);

        // Sem Sanctum::actingAs de propósito: com um TransientToken não há token desta
        // requisição para poupar, e o teste não provaria nada.
        $thisDevice = SanctumMobileToken::issue($user);

        $this->withHeader('Authorization', 'Bearer '.$thisDevice)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'senha-antiga-123',
                'password' => 'senha-nova-456',
                'password_confirmation' => 'senha-nova-456',
            ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherDevice->accessToken->getKey(),
        ]);

        // O aparelho que trocou a senha continua logado.
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => SanctumMobileToken::TOKEN_NAME,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$thisDevice)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/me/password', [
            'current_password' => 'qualquer',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertUnauthorized();
    }
}
