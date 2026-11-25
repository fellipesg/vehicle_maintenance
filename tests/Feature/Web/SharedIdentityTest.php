<?php

namespace Tests\Feature\Web;

use App\Mail\AccountDeletionRequestMail;
use App\Models\Identity;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Services\TwoFactorService;
use App\Services\User\DeleteUserAccount;
use App\Services\Vehicle\VehicleConsignmentService;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Identidade compartilhada: um e-mail, uma senha, um 2FA — e uma conta por papel.
 *
 * A propriedade que este arquivo protege é dupla. A primeira é de **regressão**: identidade com um
 * papel só (toda a base de hoje) se comporta exatamente como antes. A segunda é de **isolamento**:
 * com contas irmãs, cada papel entra pela porta dele, com o `tenant_id` dele, e excluir um não
 * leva os outros.
 */
class SharedIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth');
        RateLimiter::clear('auth-web');
    }

    // ----------------------------------------------------------------------------- modelo

    public function test_a_single_role_account_is_born_with_its_own_identity(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->assertNotNull($user->identity_id);
        $this->assertSame('dono@example.com', $user->identity->email);
        $this->assertSame([], $user->siblingAccounts()->all());
        $this->assertTrue(Hash::check('password', $user->getAuthPassword()));
    }

    public function test_sibling_accounts_share_the_password_and_keep_their_own_tenant(): void
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'pessoa@example.com',
            'password' => Hash::make('senha-compartilhada'),
        ]);
        $workshop = User::factory()->asWorkshop()->forIdentity($owner->identity)->create();

        $this->assertSame($owner->identity_id, $workshop->identity_id);
        $this->assertNotSame($owner->tenant_id, $workshop->tenant_id);
        $this->assertNotNull($workshop->tenant_id);
        $this->assertTrue(Hash::check('senha-compartilhada', $workshop->fresh()->getAuthPassword()));
        $this->assertSame('pessoa@example.com', $workshop->email);
    }

    public function test_the_same_role_cannot_repeat_under_one_identity(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        User::factory()->asUser()->forIdentity($owner->identity)->create();
    }

    // ----------------------------------------------------------------------------- login web

    public function test_each_portal_logs_in_its_own_sibling(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop', 'garage']);

        foreach ([
            // Oficina e loja da fábrica nascem aprovadas, então cada papel cai no Início dele.
            'usuario' => ['user', 'user.dashboard'],
            'oficina' => ['workshop', 'workshop.dashboard'],
            'lojista' => ['garage', 'garage.dashboard'],
        ] as $portal => [$userType, $dashboard]) {
            $this->post(route('login.submit', $portal), [
                'email' => 'pessoa@example.com',
                'password' => 'senha-boa-123',
            ])->assertRedirect(route($dashboard));

            $this->assertSame(
                $userType,
                auth()->user()->user_type,
                "O login de /{$portal} tem de logar a conta irmã de user_type {$userType}."
            );
            $this->assertSame($identity->getKey(), auth()->user()->identity_id);

            $this->post(route('logout'));
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_a_portal_the_identity_has_no_account_for_is_refused_with_the_areas_it_does_open(): void
    {
        $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'oficina' => 'workshop']);

        $this->from(route('login.lojista'))
            ->post(route('login.submit', 'lojista'), [
                'email' => 'pessoa@example.com',
                'password' => 'senha-boa-123',
            ])
            ->assertRedirect(route('login.lojista'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('suggested_portal');

        $this->assertGuest();

        $this->followingRedirects()
            ->from(route('login.lojista'))
            ->post(route('login.submit', 'lojista'), ['email' => 'pessoa@example.com', 'password' => 'senha-boa-123'])
            ->assertSee('Área do Proprietário')
            ->assertSee('Área da Oficina');
    }

    public function test_a_single_role_identity_keeps_the_old_wrong_portal_message(): void
    {
        User::factory()->asWorkshop()->create(['email' => 'oficina@example.com']);

        $this->from(route('login.usuario'))
            ->post(route('login.submit', 'usuario'), [
                'email' => 'oficina@example.com',
                'password' => 'password',
            ])
            ->assertSessionHasErrors([
                'email' => 'Esta conta é de oficina. Entre pela Área da Oficina.',
            ]);

        $this->assertGuest();
    }

    public function test_the_wrong_password_never_names_an_area(): void
    {
        $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user']);

        $this->from(route('login.usuario'))
            ->post(route('login.submit', 'usuario'), [
                'email' => 'pessoa@example.com',
                'password' => 'senha-errada',
            ])
            ->assertSessionHasErrors(['credentials' => 'E-mail ou senha incorretos.'])
            ->assertSessionMissing('suggested_portal');

        $this->assertGuest();
    }

    // ----------------------------------------------------------------------------- senha

    public function test_resetting_the_password_changes_it_for_every_sibling(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-antiga-1', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);
        $workshop = $identity->accountFor(\App\Enums\Portal::Workshop);
        $workshop->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES);

        $this->post(route('password.update'), [
            'token' => Password::createToken($identity),
            'email' => 'pessoa@example.com',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])
            // Mais de um papel: a volta é o hub, porque não há como saber por qual ela entra.
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('senha-nova-123', $owner->fresh()->getAuthPassword()));
        $this->assertTrue(Hash::check('senha-nova-123', $workshop->fresh()->getAuthPassword()));
        $this->assertSame(0, $workshop->tokens()->count(), 'A senha nova derruba os tokens das contas irmãs.');

        foreach (['usuario' => 'user.dashboard', 'oficina' => 'workshop.dashboard'] as $portal => $dashboard) {
            $this->post(route('login.submit', $portal), [
                'email' => 'pessoa@example.com',
                'password' => 'senha-nova-123',
            ])->assertRedirect(route($dashboard));

            $this->post(route('logout'));
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_changing_the_password_in_one_portal_changes_it_in_the_other(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-antiga-1', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);

        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => 'senha-antiga-1',
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ])
            ->assertRedirect(route('account.edit'));

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'pessoa@example.com',
            'password' => 'senha-nova-123',
        ])->assertRedirect(route('workshop.dashboard'));
    }

    public function test_a_sibling_can_save_my_account_and_the_email_change_reaches_every_role(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);
        $workshop = $identity->accountFor(\App\Enums\Portal::Workshop);

        // Salvar sem trocar o e-mail não pode acusar a conta irmã como "outra conta".
        $this->actingAs($owner)
            ->from(route('account.edit'))
            ->put(route('account.update'), [
                'name' => 'Pessoa Silva',
                'email' => 'pessoa@example.com',
                'phone' => '11999999999',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors();

        $this->put(route('account.update'), [
            'name' => 'Pessoa Silva',
            'email' => 'novo@example.com',
            'phone' => '11999999999',
            'current_password' => 'senha-boa-123',
        ])->assertSessionHasNoErrors();

        $this->assertSame('novo@example.com', $identity->fresh()->email);
        $this->assertSame('novo@example.com', $workshop->fresh()->email, 'O e-mail é da identidade: a irmã acompanha.');

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'novo@example.com',
            'password' => 'senha-boa-123',
        ])->assertRedirect(route('workshop.dashboard'));
    }

    // ----------------------------------------------------------------------------- 2FA

    public function test_two_factor_is_shared_by_the_siblings(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);
        $workshop = $identity->accountFor(\App\Enums\Portal::Workshop);

        $service = app(TwoFactorService::class);
        $secret = $service->generateSecretKey();
        $service->beginSetup($owner, $secret);
        $service->confirmSetup($owner, $service->hashRecoveryCodes($service->generateRecoveryCodes()));

        $this->assertTrue($owner->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(
            $workshop->fresh()->hasTwoFactorEnabled(),
            'Ligar o 2FA num papel liga em todos: a pessoa não configura o autenticador uma vez por perfil.'
        );
        $this->assertSame($secret, $workshop->fresh()->two_factor_secret);

        $service->clearTwoFactor($workshop);
        $this->assertFalse($owner->fresh()->hasTwoFactorEnabled());
    }

    // ----------------------------------------------------------------------------- exclusão

    public function test_deleting_one_role_keeps_the_others_working(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);
        $workshop = $identity->accountFor(\App\Enums\Portal::Workshop);

        app(DeleteUserAccount::class)->handle($owner);

        $this->assertDatabaseHas('identities', ['id' => $identity->getKey(), 'email' => 'pessoa@example.com']);
        $this->assertNotSame($identity->getKey(), $owner->fresh()->identity_id, 'O papel excluído sai da identidade compartilhada.');
        $this->assertSame($identity->getKey(), $workshop->fresh()->identity_id);

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
        ])->assertRedirect(route('workshop.dashboard'));

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login.submit', 'usuario'), [
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_deleting_the_last_role_takes_the_identity_with_it(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $identityId = $user->identity_id;

        app(DeleteUserAccount::class)->handle($user);

        $this->assertDatabaseMissing('identities', ['id' => $identityId]);
        $this->assertDatabaseMissing('identities', ['email' => 'dono@example.com']);
        $this->assertNotNull($user->fresh()->identity_id, 'A conta anonimizada continua tendo identidade, só dela.');
        $this->assertStringContainsString('@deleted.revisalog.invalid', (string) $user->fresh()->email);
    }

    public function test_the_deletion_mail_lists_one_link_per_role(): void
    {
        Mail::fake();
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);

        $this->post(route('account-deletion.request'), ['email' => 'pessoa@example.com'])
            ->assertRedirect(route('account-deletion.show'));

        Mail::assertQueued(AccountDeletionRequestMail::class, function (AccountDeletionRequestMail $mail) use ($identity): bool {
            $labels = array_column($mail->confirmations, 'label');

            return count($mail->confirmations) === 2
                && in_array('Proprietário', $labels, true)
                && in_array('Oficina', $labels, true)
                && str_contains(
                    (string) $mail->confirmations[0]['url'],
                    'excluir-conta/confirmar/'.$identity->accountFor(\App\Enums\Portal::Owner)->id,
                );
        });
    }

    public function test_the_confirmation_page_says_the_other_profiles_stay(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);

        $this->get(URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addHour(),
            ['user' => $owner->id, 'hash' => sha1((string) $owner->email)],
        ))
            ->assertOk()
            ->assertSee('Os seus outros perfis continuam')
            ->assertSee('Oficina');
    }

    // ----------------------------------------------------------------------------- consignação

    public function test_a_consignment_attaches_to_the_owner_role_and_not_to_a_sibling(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);
        $owner = $identity->accountFor(\App\Enums\Portal::Owner);

        $dealer = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create();

        $consignment = app(VehicleConsignmentService::class)->start($dealer, $vehicle, [
            'owner_name' => 'Pessoa',
            'owner_email' => 'pessoa@example.com',
            'owner_phone' => '11999999999',
            'owner_document' => '12345678901',
        ]);

        $this->assertInstanceOf(VehicleConsignment::class, $consignment);
        $this->assertSame(
            $owner->id,
            $consignment->owner_user_id,
            'A consignação é do papel proprietário; a conta de oficina da mesma pessoa não serve.'
        );
    }

    // ----------------------------------------------------------------------------- segundo papel

    public function test_the_owner_signup_creates_a_sibling_when_the_email_already_has_an_identity(): void
    {
        $workshop = User::factory()->asWorkshop()->create([
            'email' => 'pessoa@example.com',
            'password' => Hash::make('senha-boa-123'),
        ]);

        $this->post(route('register'), [
            'name' => 'Pessoa Silva',
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
            'password_confirmation' => 'senha-boa-123',
        ])->assertRedirect(route('user.dashboard'));

        $owner = User::query()->where('email', 'pessoa@example.com')->where('user_type', 'user')->firstOrFail();

        $this->assertSame($workshop->identity_id, $owner->identity_id);
        $this->assertNotSame($workshop->tenant_id, $owner->tenant_id);
        $this->assertTrue(Hash::check('senha-boa-123', $workshop->fresh()->getAuthPassword()));
    }

    public function test_the_owner_signup_refuses_a_new_password_for_an_email_that_already_has_an_identity(): void
    {
        User::factory()->asWorkshop()->create([
            'email' => 'pessoa@example.com',
            'password' => Hash::make('senha-boa-123'),
        ]);

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'Pessoa Silva',
                'email' => 'pessoa@example.com',
                'password' => 'outra-senha-456',
                'password_confirmation' => 'outra-senha-456',
            ])
            ->assertSessionHasErrors(['password' => \App\Rules\MatchesIdentityPassword::MESSAGE]);

        $this->assertDatabaseMissing('users', ['email' => 'pessoa@example.com', 'user_type' => 'user']);
    }

    public function test_the_owner_signup_still_refuses_a_second_owner_account(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'Outro',
                'email' => 'dono@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors(['email' => trans('validation.custom.email.unique')]);

        $this->assertSame(1, User::query()->where('email', 'dono@example.com')->count());
    }

    public function test_the_workshop_signup_creates_a_sibling_for_an_owner_account(): void
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'pessoa@example.com',
            'password' => Hash::make('senha-boa-123'),
        ]);

        $this->post(route('workshops.signup.store'), [
            'name' => 'Pessoa Silva',
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
            'password_confirmation' => 'senha-boa-123',
            'trade_name' => 'Oficina da Pessoa',
            'cnpj' => '19131243000197',
            'phone' => '11999999999',
            'cep' => '86010000',
            'street' => 'Rua A',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Londrina',
            'state' => 'PR',
            // Oficina nova entra em análise e cai no próprio cadastro (WorkshopSelfSignupTest).
        ])->assertRedirect(route('workshop.profile.show'));

        $workshop = User::query()->where('email', 'pessoa@example.com')->where('user_type', 'workshop')->firstOrFail();

        $this->assertSame($owner->identity_id, $workshop->identity_id);
        $this->assertNotSame($owner->tenant_id, $workshop->tenant_id);
    }

    // ----------------------------------------------------------------------------- API

    public function test_the_api_login_keeps_answering_the_same_for_a_single_role_identity(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'dono@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'dono@example.com');
    }

    public function test_the_api_login_picks_the_sibling_of_the_portal_it_is_given(): void
    {
        $identity = $this->identityWithRoles('pessoa@example.com', 'senha-boa-123', ['user', 'workshop']);

        $this->postJson('/api/v1/login', [
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
            'portal' => 'oficina',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $identity->accountFor(\App\Enums\Portal::Workshop)->id);

        $this->postJson('/api/v1/login', [
            'email' => 'pessoa@example.com',
            'password' => 'senha-boa-123',
            'portal' => 'lojista',
        ])->assertStatus(403);
    }

    /**
     * Identidade com um papel por `user_type` pedido, todos com a mesma senha.
     *
     * @param  list<string>|array<string, string>  $userTypes
     */
    private function identityWithRoles(string $email, string $password, array $userTypes): Identity
    {
        $first = User::factory()
            ->state(['user_type' => array_values($userTypes)[0]])
            ->create(['email' => $email, 'password' => Hash::make($password)]);

        foreach (array_slice(array_values($userTypes), 1) as $userType) {
            User::factory()
                ->state(['user_type' => $userType])
                ->forIdentity($first->identity)
                ->create();
        }

        return $first->identity->fresh();
    }
}
