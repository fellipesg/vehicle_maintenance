<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\SanctumMobileToken;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('password-reset-link-ip:127.0.0.1');
        RateLimiter::clear('password-reset:127.0.0.1');
    }

    public function test_every_portal_login_links_to_password_recovery_for_that_portal(): void
    {
        foreach (['usuario', 'lojista', 'oficina', 'admin'] as $portal) {
            $this->get(route("login.{$portal}"))
                ->assertOk()
                ->assertSee('Esqueci minha senha')
                ->assertSee('href="'.e(route('password.request', ['portal' => $portal])).'"', false);
        }
    }

    public function test_forgot_password_page_renders_in_the_guest_layout(): void
    {
        $this->get(route('password.request', ['portal' => 'lojista']))
            ->assertOk()
            ->assertSee('<title>Esqueci minha senha', false)
            ->assertSee('data-slot="page-header-title" class="text-2xl font-bold tracking-tight text-balance text-foreground sm:text-3xl">Esqueci minha senha</h1>', false)
            ->assertSee('<main id="conteudo" tabindex="-1"', false)
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('O link vale por 60 minutos.')
            ->assertSee('href="'.route('login.lojista').'"', false);
    }

    public function test_forgot_password_back_link_ignores_unknown_portal(): void
    {
        $this->get(route('password.request', ['portal' => 'https://evil.example']))
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('evil.example');
    }

    public function test_requesting_a_link_sends_the_portuguese_reset_email(): void
    {
        Notification::fake();
        $user = User::factory()->asGarage()->create(['name' => 'Maria Souza', 'email' => 'maria@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'maria@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', PasswordResetController::LINK_REQUESTED_MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $html = (string) $mail->render();

            return $mail->subject === 'Redefinir sua senha na RevisaLog'
                && $mail->salutation === 'RevisaLog'
                && $notification->resetUrl($user) === route('password.reset', ['token' => $notification->token, 'email' => 'maria@example.com'])
                && Password::tokenExists($user, $notification->token)
                && str_contains($html, 'Olá, Maria!')
                && str_contains($html, 'Criar nova senha')
                && str_contains($html, 'O link vale por 60 minutos')
                && str_contains($html, 'Se o botão não funcionar')
                && ! str_contains($html, "If you're having trouble")
                && ! str_contains($html, 'Regards');
        });

        $this->get(route('password.request'))
            ->assertSee('data-flash="status"', false)
            ->assertSee('Se este e-mail estiver cadastrado, você receberá um link em alguns minutos.')
            ->assertSee('value="maria@example.com"', false);
    }

    public function test_reset_notification_is_queued_and_uses_the_project_mail_chrome(): void
    {
        $user = User::factory()->asUser()->create();
        $notification = new ResetPasswordNotification('token-de-teste');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via($user));
        $this->assertStringContainsString('RevisaLog', (string) $notification->toMail($user)->render());
    }

    public function test_unknown_email_gets_the_same_neutral_answer_and_no_email(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ninguem@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', PasswordResetController::LINK_REQUESTED_MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_second_request_within_a_minute_keeps_the_neutral_answer(): void
    {
        Notification::fake();
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'dono@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'dono@example.com'])
            ->assertSessionHas('status', PasswordResetController::LINK_REQUESTED_MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_link_request_validates_the_email_in_portuguese(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nao-e-email'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => 'Informe um e-mail válido, como nome@exemplo.com.']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => ''])
            ->assertSessionHasErrors(['email' => 'Informe o e-mail da sua conta.']);
    }

    public function test_link_requests_are_throttled_per_email(): void
    {
        Notification::fake();
        RateLimiter::clear('password-reset-link:alguem@example.com|127.0.0.1');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => 'alguem@example.com'])
                ->assertSessionHasNoErrors();
        }

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'alguem@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');

        $this->assertStringStartsWith('Muitas tentativas seguidas. Aguarde', session('errors')->first('email'));
    }

    public function test_reset_page_shows_the_form_with_the_email_from_the_link(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'dono@example.com']))
            ->assertOk()
            ->assertSee('<title>Criar nova senha', false)
            ->assertSee('name="token" value="'.$token.'"', false)
            ->assertSee('value="dono@example.com"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-describedby="password-criteria"', false)
            ->assertDontSee('Este link expirou ou já foi usado.');
    }

    public function test_reset_page_explains_an_expired_link_before_asking_for_a_password(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->get(route('password.reset', ['token' => 'token-invalido', 'email' => 'dono@example.com']))
            ->assertOk()
            ->assertSee('Este link expirou ou já foi usado.')
            ->assertSee('href="'.route('password.request').'"', false)
            ->assertDontSee('action="'.route('password.update').'"', false);
    }

    public function test_valid_token_resets_the_password_and_sends_to_the_account_portal_login(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->asWorkshop()->create(['email' => 'oficina@example.com']);
        $oldRememberToken = $user->remember_token;
        $user->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'oficina@example.com',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])
            ->assertRedirect(route('login.oficina'))
            ->assertSessionHas('status', PasswordResetController::PASSWORD_RESET_MESSAGE)
            ->assertSessionHasInput('email', 'oficina@example.com');

        $user->refresh();

        $this->assertTrue(Hash::check('senha-nova-123', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertSame(0, $user->tokens()->count(), 'Os tokens do app saem com a senha antiga.');
        $this->assertGuest();
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'oficina@example.com',
            'password' => 'senha-nova-123',
        ])->assertRedirect(route('workshop.dashboard'));
    }

    public function test_resetting_the_password_signs_out_sessions_open_on_other_devices(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'dono@example.com', 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));
        $otherDevice = session()->all();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => 'dono@example.com',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertRedirect(route('login.usuario'));

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withSession($otherDevice)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    public function test_invalid_token_does_not_change_the_password(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        Password::createToken($user);

        $this->from(route('password.reset', ['token' => 'token-errado', 'email' => 'dono@example.com']))
            ->post(route('password.update'), [
                'token' => 'token-errado',
                'email' => 'dono@example.com',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'token-errado', 'email' => 'dono@example.com']))
            ->assertSessionHasErrors(['token' => PasswordResetController::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_token_for_another_email_is_rejected_with_the_same_message(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        User::factory()->asUser()->create(['email' => 'outro@example.com']);
        $token = Password::createToken($owner);

        $this->from(route('password.reset', ['token' => $token]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => 'outro@example.com',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertSessionHasErrors(['token' => PasswordResetController::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
    }

    public function test_new_password_is_validated_in_portuguese(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $token = Password::createToken($user);

        $this->from(route('password.reset', ['token' => $token]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => 'dono@example.com',
                'password' => 'curta',
                'password_confirmation' => 'outra',
            ])
            ->assertSessionHasErrors(['password' => 'A senha deve ter no mínimo 8 caracteres.']);

        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_reset_submissions_are_throttled_per_device(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->from(route('password.request'))->post(route('password.update'), [
                'token' => 'token-'.$attempt,
                'email' => 'dono@example.com',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ]);
        }

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => 'token-11',
                'email' => 'dono@example.com',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertSessionHasErrors('token');

        $this->assertStringStartsWith('Muitas tentativas seguidas.', session('errors')->first('token'));
    }

    public function test_logged_in_user_opening_password_recovery_goes_to_own_home(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->get(route('password.request'))
            ->assertRedirect(route('garage.dashboard'));
    }
}
