<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AccountEmailChangedNotification;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * "Minha conta" (/conta): dados pessoais, troca de senha e exclusão que anonimiza.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->put(route('account.update'))->assertRedirect(route('login'));
        $this->delete(route('account.destroy'))->assertRedirect(route('login'));
    }

    public function test_every_profile_can_open_the_account_page(): void
    {
        $accounts = [
            'Proprietário' => User::factory()->asUser()->create(),
            'Lojista' => User::factory()->asGarage()->create(),
            'Oficina' => User::factory()->asWorkshop()->create(),
            'Administrador' => User::factory()->asUser()->asAdmin()->create(),
        ];

        foreach ($accounts as $label => $user) {
            $this->actingAs($user)
                ->get(route('account.edit'))
                ->assertOk()
                ->assertSee('<title>Minha conta', false)
                ->assertSee("Conta de {$label}")
                ->assertSee('value="'.e($user->email).'"', false);
        }
    }

    public function test_account_page_has_the_three_sections_and_a_confirmed_deletion(): void
    {
        $owner = User::factory()->asUser()->create(['name' => 'Ana Lima', 'phone' => '43999998888']);

        $response = $this->actingAs($owner)->get(route('account.edit'))->assertOk();

        $response
            ->assertSee('Minha conta')
            ->assertSee('aria-current="page"', false)
            ->assertSee('value="Ana Lima"', false)
            ->assertSee('value="43999998888"', false)
            ->assertSee('id="dados"', false)
            ->assertSee('aria-labelledby="dados-titulo"', false)
            ->assertSee('action="'.route('account.password').'"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('id="delete_account_password"', false)
            ->assertSee('for="delete_account_password"', false)
            ->assertSee('data-confirm="Seus dados pessoais serão apagados', false)
            ->assertSee('data-confirm-variant="danger"', false)
            ->assertSee('Excluir minha conta')
            ->assertSee('as manutenções continuam no histórico de cada veículo, ligadas ao chassi', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="password"'));
    }

    public function test_workshop_is_told_its_directory_profile_stays(): void
    {
        $this->actingAs(User::factory()->asWorkshop()->create())
            ->get(route('account.edit'))
            ->assertSee('o perfil da oficina e as ordens de serviço continuam');

        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('account.edit'))
            ->assertDontSee('o perfil da oficina e as ordens de serviço continuam');
    }

    public function test_user_updates_name_email_and_phone(): void
    {
        Notification::fake();
        $owner = User::factory()->asUser()->create(['email' => 'antigo@example.com']);

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.update'), [
                'name' => '  Ana Lima  ',
                'email' => 'nova@example.com',
                'phone' => '(43) 99999-8888',
                'current_password' => 'password',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('success', 'Dados da conta atualizados.')
            ->assertSessionHasNoErrors();

        $owner->refresh();

        $this->assertSame('Ana Lima', $owner->name);
        $this->assertSame('nova@example.com', $owner->email);
        $this->assertSame('43999998888', $owner->phone);
        $this->assertNull($owner->email_verified_at);

        Notification::assertSentOnDemand(
            AccountEmailChangedNotification::class,
            function (AccountEmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                $mail = $notification->toMail($notifiable);
                $body = implode(' ', $mail->introLines);

                return $notifiable->routes['mail'] === 'antigo@example.com'
                    && $mail->subject === 'O e-mail da sua conta na RevisaLog foi alterado'
                    && $mail->greeting === 'Olá, Ana!'
                    && $mail->salutation === 'RevisaLog'
                    && str_contains($body, 'no***@example.com')
                    && ! str_contains($body, 'nova@example.com');
            },
        );
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        Notification::fake();
        $owner = User::factory()->asUser()->create(['name' => 'Ana Lima', 'email' => 'dono@example.com']);

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.update'), ['name' => 'Ana Lima', 'email' => 'invasor@example.com'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors(['current_password' => 'Para trocar o e-mail, informe sua senha atual.']);

        $this->actingAs($owner)
            ->put(route('account.update'), ['name' => 'Ana Lima', 'email' => 'invasor@example.com', 'current_password' => 'errada'])
            ->assertSessionHasErrors(['current_password' => 'A senha atual não confere.']);

        $this->assertSame('dono@example.com', $owner->fresh()->email);
        Notification::assertNothingSent();

        $html = $this->actingAs($owner)->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dados_current_password-error"', $html);
        $this->assertStringContainsString('A senha atual não confere.', $html);
    }

    public function test_name_and_phone_changes_do_not_ask_for_the_password(): void
    {
        Notification::fake();
        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->actingAs($owner)
            ->put(route('account.update'), ['name' => 'Ana Lima', 'email' => 'dono@example.com', 'phone' => '43999998888'])
            ->assertSessionHasNoErrors();

        $this->assertSame('43999998888', $owner->fresh()->phone);

        // Senha errada preenchida pelo gerenciador não barra quem não troca o e-mail.
        $this->actingAs($owner)
            ->put(route('account.update'), ['name' => 'Ana L.', 'email' => 'dono@example.com', 'current_password' => 'errada'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ana L.', $owner->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_account_page_asks_for_the_password_only_to_change_the_email(): void
    {
        $html = $this->actingAs(User::factory()->asUser()->create())->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('for="dados_current_password"', $html);
        $this->assertStringContainsString('id="dados_current_password"', $html);
        $this->assertStringContainsString('Obrigatória só para trocar o e-mail.', $html);
        $this->assertSame(1, substr_count($html, 'id="current_password"'), 'O cartão Senha mantém o próprio id.');
        $this->assertStringContainsString('Depois da troca, os outros aparelhos saem da conta; este continua conectado.', $html);
    }

    public function test_account_update_is_throttled(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        RateLimiter::clear('account-update'.sha1((string) $owner->id));

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($owner)->put(route('account.update'), ['name' => 'Ana', 'email' => 'outro@example.com', 'current_password' => 'errada']);
        }

        $this->actingAs($owner)
            ->put(route('account.update'), ['name' => 'Ana', 'email' => 'outro@example.com', 'current_password' => 'errada'])
            ->assertStatus(429);
    }

    public function test_keeping_the_same_email_keeps_it_verified(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->actingAs($owner)
            ->put(route('account.update'), ['name' => 'Outro Nome', 'email' => 'dono@example.com', 'phone' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($owner->fresh()->email_verified_at);
        $this->assertNull($owner->fresh()->phone);
    }

    public function test_update_validates_in_portuguese(): void
    {
        User::factory()->asUser()->create(['email' => 'ocupado@example.com']);
        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.update'), ['name' => '', 'email' => 'ocupado@example.com', 'phone' => '123', 'current_password' => 'password'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors([
                'name' => 'Informe seu nome.',
                'email' => 'Este e-mail já é usado por outra conta.',
                'phone' => 'Informe o telefone com DDD, só números (10 ou 11 dígitos).',
            ]);

        $this->assertSame('dono@example.com', $owner->fresh()->email);

        $this->actingAs($owner)
            ->get(route('account.edit'))
            ->assertSee('id="dados-erros"', false)
            ->assertSee('Revise 3 campos antes de continuar')
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_user_changes_password_with_the_current_one(): void
    {
        $owner = User::factory()->asUser()->create();
        $oldRememberToken = $owner->remember_token;

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $owner->refresh();

        $this->assertTrue(Hash::check('senha-nova-123', $owner->password));
        $this->assertNotSame($oldRememberToken, $owner->remember_token);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_wrong_current_password_keeps_the_old_one_and_shows_errors_in_the_password_card(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => 'errada',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'A senha atual não confere.']);

        $this->assertTrue(Hash::check('password', $owner->fresh()->password));

        $html = $this->actingAs($owner)->get(route('account.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="senha-erros"', $html);
        $this->assertStringContainsString('href="#current_password"', $html);
        $this->assertStringContainsString('A senha atual não confere.', $html);
        $this->assertStringNotContainsString('id="dados-erros"', $html);
        $this->assertStringNotContainsString('id="exclusao-erros"', $html);
    }

    public function test_new_password_rules(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'A senha nova precisa ser diferente da atual.']);

        $this->actingAs($owner)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'curta',
                'password_confirmation' => 'curta',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'A senha nova deve ter no mínimo 8 caracteres.']);
    }

    public function test_password_change_is_throttled(): void
    {
        $owner = User::factory()->asUser()->create();
        RateLimiter::clear(sha1((string) $owner->id));

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($owner)->put(route('account.password'), ['current_password' => 'errada']);
        }

        $this->actingAs($owner)
            ->put(route('account.password'), ['current_password' => 'errada'])
            ->assertStatus(429)
            ->assertSee('Muitas tentativas seguidas');
    }

    public function test_deleting_the_account_anonymizes_it_and_keeps_vehicle_history(): void
    {
        $owner = User::factory()->asUser()->create([
            'name' => 'Ana Lima',
            'email' => 'ana@example.com',
            'phone' => '43999998888',
        ]);
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->fresh()->tenant_id,
        ]);
        $owner->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES);

        $this->actingAs($owner)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $this->assertGuest();

        $owner->refresh();

        $this->assertSame('Conta excluída', $owner->name);
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $owner->email);
        $this->assertNull($owner->phone);
        $this->assertFalse($owner->vehicles()->exists());
        $this->assertSame(0, $owner->tokens()->count());
        $this->assertModelExists($vehicle);
        $this->assertModelExists($maintenance->fresh());
        $this->assertSame($owner->id, $maintenance->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'ana@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('credentials');
        $this->assertGuest();
    }

    public function test_deleting_requires_the_current_password(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'ana@example.com']);

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->delete(route('account.destroy'), ['password' => 'errada'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrorsIn('deleteAccount', ['password' => 'A senha não confere.']);

        $this->assertAuthenticatedAs($owner);
        $this->assertSame('ana@example.com', $owner->fresh()->email);

        $html = $this->actingAs($owner)->get(route('account.edit'))->getContent();

        $this->assertStringContainsString('id="exclusao-erros"', $html);
        $this->assertStringContainsString('href="#delete_account_password"', $html);
        $this->assertStringContainsString('id="delete_account_password-error"', $html);
    }

    public function test_deleting_the_account_signs_out_the_other_devices(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'ana@example.com']);
        $otherDevice = $this->signInOnAnotherDevice('ana@example.com');

        $this->actingAs($owner)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->leaveDevice();

        $this->withSession($otherDevice)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    public function test_changing_the_password_signs_out_the_other_devices_and_keeps_this_one(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'ana@example.com']);
        $otherDevice = $this->signInOnAnotherDevice('ana@example.com');
        $thisDevice = $this->signInOnAnotherDevice('ana@example.com');

        $this->withSession($thisDevice)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors();

        $thisDeviceAfterChange = session()->all();
        $this->leaveDevice();

        $this->withSession($thisDeviceAfterChange)->get(route('account.edit'))->assertOk();
        $this->assertAuthenticatedAs($owner);
        $this->leaveDevice();

        $this->withSession($otherDevice)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    /**
     * Entra pelo login do portal do proprietário e devolve a sessão desse aparelho. Depois limpa
     * guarda e sessão, como se a próxima requisição viesse de outro navegador.
     *
     * @return array<string, mixed>
     */
    private function signInOnAnotherDevice(string $email): array
    {
        $this->post(route('login.submit', 'usuario'), ['email' => $email, 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));

        $deviceSession = session()->all();
        $this->leaveDevice();

        return $deviceSession;
    }

    private function leaveDevice(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
