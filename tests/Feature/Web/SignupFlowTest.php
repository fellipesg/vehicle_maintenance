<?php

namespace Tests\Feature\Web;

use App\Enums\Portal;
use App\Http\Middleware\EnsureSignupStep;
use App\Models\User;
use App\Rules\MatchesIdentityPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Cadastro em etapas (/cadastro): perfil e e-mail, senha, dados do perfil.
 *
 * A etapa 1 é onde a busca do e-mail acontece, e é o que separa os dois caminhos: e-mail novo cria
 * identidade, e-mail que já tem conta cria **conta irmã** e para isso pede a senha daquela conta.
 */
class SignupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('signup-ip|127.0.0.1');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function workshopDetails(array $overrides = []): array
    {
        return [
            'name' => 'Rillian Pires', 'trade_name' => 'Rillian Mecânica',
            'cnpj' => '19131243000197', 'phone' => '11999999999', 'cep' => '86010000',
            'street' => 'Rua Demonstração', 'number' => '100', 'neighborhood' => 'Centro',
            'city' => 'Londrina', 'state' => 'PR',
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------ primeiro cadastro

    public function test_the_first_signup_creates_the_owner_account_and_its_identity(): void
    {
        $this->get(route('signup.start'))->assertOk()->assertSee('Proprietário de veículo');

        $this->post(route('signup.profile'), ['profile' => 'usuario', 'email' => 'rillian@teste.dev'])
            ->assertRedirect(route('signup.password'));

        $this->get(route('signup.password'))
            ->assertOk()
            ->assertSee('Crie sua senha')
            ->assertSee('Confirmar senha');

        $this->post(route('signup.password.store'), [
            'password' => 'senha-do-rillian', 'password_confirmation' => 'senha-do-rillian',
        ])->assertRedirect(route('signup.details'));

        $this->get(route('signup.details'))->assertOk()->assertSee('CPF ou CNPJ');

        $this->post(route('signup.details.store'), ['name' => 'Rillian Pires', 'phone' => '11999999999'])
            ->assertRedirect(route('user.dashboard'));

        $owner = User::query()->where('email', 'rillian@teste.dev')->sole();

        $this->assertSame('user', $owner->user_type);
        $this->assertNotNull($owner->identity_id);
        $this->assertNotNull($owner->tenant_id);
        $this->assertTrue(Hash::check('senha-do-rillian', $owner->getAuthPassword()));
        $this->assertAuthenticatedAs($owner);
    }

    // ------------------------------------------------------------------ segundo perfil

    public function test_the_second_profile_asks_for_the_password_of_the_account_that_already_exists(): void
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'rillian@teste.dev', 'password' => Hash::make('senha-do-rillian'),
        ]);

        $this->post(route('signup.profile'), ['profile' => 'oficina', 'email' => 'rillian@teste.dev'])
            ->assertRedirect(route('signup.password'));

        // A busca achou a conta: a tela pede a senha dela, não uma nova.
        $this->get(route('signup.password'))
            ->assertOk()
            ->assertSee('Confirme sua senha')
            ->assertSee('Este e-mail já tem conta na RevisaLog.')
            ->assertSee('Senha da sua conta')
            ->assertSee(route('password.request'), false)
            ->assertDontSee('Confirmar senha');

        $this->post(route('signup.password.store'), ['password' => 'senha-do-rillian'])
            ->assertRedirect(route('signup.details'));

        $this->get(route('signup.details'))->assertOk()->assertSee('Nome fantasia');

        $this->post(route('signup.details.store'), $this->workshopDetails())
            ->assertRedirect(route('workshop.profile.show'));

        $workshop = User::query()->where('email', 'rillian@teste.dev')->where('user_type', 'workshop')->sole();

        $this->assertSame($owner->identity_id, $workshop->identity_id, 'O segundo perfil é conta irmã da mesma identidade.');
        $this->assertNotSame($owner->tenant_id, $workshop->tenant_id, 'Cada perfil tem o tenant dele.');
        $this->assertTrue(Hash::check('senha-do-rillian', $owner->fresh()->getAuthPassword()), 'A senha da identidade não foi trocada.');
        $this->assertDatabaseHas('workshops', ['cnpj' => '19131243000197', 'user_id' => $workshop->id]);
        $this->assertAuthenticatedAs($workshop);
    }

    public function test_the_wrong_password_stops_the_second_profile(): void
    {
        User::factory()->asUser()->create([
            'email' => 'rillian@teste.dev', 'password' => Hash::make('senha-do-rillian'),
        ]);

        $this->post(route('signup.profile'), ['profile' => 'oficina', 'email' => 'rillian@teste.dev']);

        $this->from(route('signup.password'))
            ->post(route('signup.password.store'), ['password' => 'chute-errado'])
            ->assertRedirect(route('signup.password'))
            ->assertSessionHasErrors(['password' => MatchesIdentityPassword::MESSAGE]);

        // Sem a senha não há etapa 3: quem esqueceu recupera a senha e volta.
        $this->get(route('signup.details'))
            ->assertRedirect(route('signup.start'))
            ->assertSessionHas('warning', EnsureSignupStep::EXPIRED_MESSAGE);

        $this->assertSame(1, User::query()->where('email', 'rillian@teste.dev')->count());
    }

    public function test_the_profile_the_email_already_has_is_refused_at_the_first_step(): void
    {
        User::factory()->asUser()->create(['email' => 'rillian@teste.dev']);

        $this->from(route('signup.start'))
            ->post(route('signup.profile'), ['profile' => 'usuario', 'email' => 'rillian@teste.dev'])
            ->assertRedirect(route('signup.start'))
            ->assertSessionHasErrors(['email' => trans('validation.custom.email.unique')]);
    }

    // ------------------------------------------------------------------ guardas das etapas

    public function test_the_steps_cannot_be_skipped(): void
    {
        foreach ([route('signup.password'), route('signup.details')] as $url) {
            $this->get($url)
                ->assertRedirect(route('signup.start'))
                ->assertSessionHas('warning', EnsureSignupStep::EXPIRED_MESSAGE);
        }
    }

    public function test_the_password_is_never_kept_in_the_session_in_clear_text(): void
    {
        $this->post(route('signup.profile'), ['profile' => 'usuario', 'email' => 'rillian@teste.dev']);
        $this->post(route('signup.password.store'), [
            'password' => 'senha-do-rillian', 'password_confirmation' => 'senha-do-rillian',
        ]);

        $state = session('signup');

        $this->assertIsArray($state);
        $this->assertNotContains('senha-do-rillian', $state, 'A senha em claro nunca entra na sessão.');
        $this->assertTrue(Hash::check('senha-do-rillian', (string) $state['password_hash']));
    }

    public function test_the_flow_is_cleared_once_the_account_exists(): void
    {
        $this->post(route('signup.profile'), ['profile' => 'usuario', 'email' => 'rillian@teste.dev']);
        $this->post(route('signup.password.store'), [
            'password' => 'senha-do-rillian', 'password_confirmation' => 'senha-do-rillian',
        ]);
        $this->post(route('signup.details.store'), ['name' => 'Rillian Pires']);

        $this->assertNull(session('signup'), 'O fluxo sai da sessão com a conta criada.');
    }

    // ------------------------------------------------------------------ lojista

    public function test_the_dealer_is_shown_but_sent_to_the_social_login(): void
    {
        $this->get(route('signup.start'))
            ->assertOk()
            ->assertSee('Lojista')
            ->assertSee(route('login.lojista'), false);

        // E não se cadastra por aqui, mesmo quem mandar o slug na mão.
        $this->from(route('signup.start'))
            ->post(route('signup.profile'), ['profile' => 'lojista', 'email' => 'loja@teste.dev'])
            ->assertSessionHasErrors('profile');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_guesses_are_throttled_in_their_own_bucket(): void
    {
        User::factory()->asUser()->create([
            'email' => 'rillian@teste.dev', 'password' => Hash::make('senha-do-rillian'),
        ]);

        $this->post(route('signup.profile'), ['profile' => 'oficina', 'email' => 'rillian@teste.dev']);

        // O balde é o `signup`, não o de login: adivinhar aqui não é de graça, e tentar aqui
        // também não gasta a cota de quem está entrando em /login com esse e-mail.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('signup.password.store'), ['password' => 'chute-'.$attempt]);
        }

        $this->post(route('signup.password.store'), ['password' => 'senha-do-rillian'])
            ->assertStatus(429);

        $this->assertSame(1, User::query()->where('email', 'rillian@teste.dev')->count());
    }

    // ------------------------------------------------------------------ requisito funcional

    public function test_a_logged_in_account_cannot_reach_the_signup_to_switch_profiles(): void
    {
        $owner = User::factory()->asUser()->create(['email' => 'rillian@teste.dev']);

        $this->actingAs($owner);

        foreach ([
            route('signup.start'), route('signup.password'), route('signup.details'),
            route('register'), route('workshops.signup'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('user.dashboard'));
        }

        $this->post(route('signup.profile'), ['profile' => 'oficina', 'email' => 'rillian@teste.dev'])
            ->assertRedirect(route('user.dashboard'));

        $this->assertSame(1, User::query()->where('email', 'rillian@teste.dev')->count(),
            'Conta logada não cria perfil nenhum: para isso é preciso sair e entrar de novo.');
    }

    public function test_a_logged_in_owner_is_offered_no_way_into_its_sibling_profile(): void
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'rillian@teste.dev', 'password' => Hash::make('senha-do-rillian'),
        ]);
        $workshop = User::factory()->asWorkshop()->forIdentity($owner->identity)->create();

        $this->actingAs($owner);

        // O menu de conta não oferece a área da irmã...
        $this->assertSame([Portal::Owner], Portal::accessibleBy($owner));
        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee(route('workshop.dashboard'), false);

        // ...e a área dela não abre: o 403 de perfil errado volta para o Início da conta.
        $this->get(route('workshop.dashboard'))->assertRedirect(route('user.dashboard'));

        // Só saindo e entrando pela porta da oficina.
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'rillian@teste.dev', 'password' => 'senha-do-rillian',
        ])->assertRedirect(route('workshop.dashboard'));

        $this->assertAuthenticatedAs($workshop);
    }
}
