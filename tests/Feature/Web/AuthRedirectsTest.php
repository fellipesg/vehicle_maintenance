<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\FriendlyHttpErrors;
use App\Support\PortalAccess;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Para onde vai cada pessoa: visitante num link protegido, login com url.intended, perfil errado
 * (403) e sessão expirada (419).
 */
class AuthRedirectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-web');
    }

    public function test_guest_on_a_protected_link_goes_to_the_login_of_that_portal(): void
    {
        $this->get('/garagem/estoque')->assertRedirect(route('login.lojista'));
        $this->get('/oficina/manutencoes')->assertRedirect(route('login.oficina'));
        $this->get('/admin/dashboard')->assertRedirect(route('login.admin'));
        $this->get('/usuario/veiculos')->assertRedirect(route('login.usuario'));
        $this->get('/conta')->assertRedirect(route('login'));
        $this->get('/notificacoes')->assertRedirect(route('login'));
    }

    public function test_login_page_tells_the_visitor_they_will_continue_where_they_were(): void
    {
        $this->get('/garagem/estoque');

        $this->get(route('login.lojista'))
            ->assertOk()
            ->assertSee('Entre para continuar de onde parou.');

        $this->flushSession();

        $this->get(route('login.lojista'))
            ->assertOk()
            ->assertDontSee('Entre para continuar de onde parou.');
    }

    public function test_workshop_login_ignores_intended_admin_url_and_goes_to_workshop_home(): void
    {
        $workshop = User::factory()->asWorkshop()->create(['email' => 'oficina@example.com']);

        $this->get('/admin/dashboard')->assertRedirect(route('login.admin'));

        $this->post(route('login.submit', 'oficina'), [
            'email' => 'oficina@example.com',
            'password' => 'password',
        ])->assertRedirect(route('workshop.dashboard'));

        $this->assertAuthenticatedAs($workshop);
        $this->assertNull(session('url.intended'));

        $this->get(route('workshop.dashboard'))->assertOk();
    }

    public function test_login_returns_to_the_intended_page_of_the_own_portal(): void
    {
        User::factory()->asGarage()->create(['email' => 'loja@example.com']);

        $this->get('/garagem/estoque')->assertRedirect(route('login.lojista'));

        $this->post(route('login.submit', 'lojista'), [
            'email' => 'loja@example.com',
            'password' => 'password',
        ])->assertRedirect(url('/garagem/estoque'));
    }

    public function test_login_returns_to_shared_pages_like_account(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->get('/conta')->assertRedirect(route('login'));

        $this->post(route('login.submit', 'usuario'), [
            'email' => 'dono@example.com',
            'password' => 'password',
        ])->assertRedirect(url('/conta'));
    }

    public function test_admin_can_return_to_the_admin_panel_after_owner_login(): void
    {
        User::factory()->asUser()->asAdmin()->create(['email' => 'admin@example.com']);

        $this->get('/admin/marcas');

        $this->post(route('login.submit', 'usuario'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(url('/admin/marcas'));
    }

    public function test_intended_url_on_another_host_is_ignored(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->withSession(['url.intended' => 'https://evil.example/usuario/dashboard'])
            ->post(route('login.submit', 'usuario'), [
                'email' => 'dono@example.com',
                'password' => 'password',
            ])->assertRedirect(route('user.dashboard'));
    }

    public function test_logged_in_user_opening_login_goes_to_own_home(): void
    {
        $this->actingAs(User::factory()->asWorkshop()->create())
            ->get(route('login'))
            ->assertRedirect(route('workshop.dashboard'));

        $this->actingAs(User::factory()->asUser()->asAdmin()->create())
            ->get(route('login.usuario'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_profile_is_redirected_to_own_home_with_explanation(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->get('/usuario/dashboard')
            ->assertRedirect(route('garage.dashboard'))
            ->assertSessionHas('info', PortalAccess::wrongAreaMessage('usuario'));

        $this->actingAs($garage)
            ->followingRedirects()
            ->get('/oficina/manutencoes')
            ->assertOk()
            ->assertSee('data-flash="info"', false)
            ->assertSee('Essa página fica na Área da Oficina, que não faz parte desta conta. Trouxemos você para o seu Início.');
    }

    public function test_non_admin_opening_admin_panel_goes_to_own_home(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('info', 'Essa página fica no Painel Administrador, que não faz parte desta conta. Trouxemos você para o seu Início.');
    }

    public function test_forbidden_record_inside_own_area_keeps_the_portuguese_error_page(): void
    {
        $garage = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertForbidden()
            ->assertSee('Você não tem acesso a esta página')
            ->assertSee('href="'.route('garage.dashboard').'"', false)
            ->assertSee('Ir para o Início')
            ->assertDontSee('Forbidden');
    }

    public function test_api_forbidden_stays_json(): void
    {
        $this->actingAs(User::factory()->asGarage()->create())
            ->getJson('/usuario/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_expired_session_on_login_returns_to_the_form_with_the_email(): void
    {
        $this->enforceCsrf();

        $this->post(route('login.submit', 'lojista'), [
            'email' => 'loja@example.com',
            'password' => 'segredo123',
        ])
            ->assertRedirect(route('login.lojista'))
            ->assertSessionHas('warning', FriendlyHttpErrors::SESSION_EXPIRED_MESSAGE)
            ->assertSessionHasInput('email', 'loja@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->get(route('login.lojista'))
            ->assertOk()
            ->assertSee('data-flash="warning"', false)
            ->assertSee('Sua sessão expirou por segurança.')
            ->assertSee('value="loja@example.com"', false);
    }

    public function test_expired_session_on_register_and_password_forms_returns_to_each_form(): void
    {
        $this->enforceCsrf();

        $this->post('/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x', 'password_confirmation' => 'x'])
            ->assertRedirect(route('register'))
            ->assertSessionHasInput('name', 'Ana')
            ->assertSessionMissing('_old_input.password');

        $this->post(route('password.email'), ['email' => 'ana@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('warning', FriendlyHttpErrors::SESSION_EXPIRED_MESSAGE);

        $this->post(route('password.update'), ['token' => 'abc', 'email' => 'ana@example.com', 'password' => 'x'])
            ->assertRedirect(route('password.reset', ['token' => 'abc']))
            ->assertSessionMissing('_old_input.token');

        $this->post(route('contact.store'), ['name' => 'Ana', 'message' => 'Olá'])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHasInput('message', 'Olá');
    }

    public function test_expired_session_on_logout_explains_what_happened(): void
    {
        $this->enforceCsrf();

        $this->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('info', 'Sua sessão já tinha expirado, então você já está fora da conta.');

        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->post(route('logout'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('warning');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_expired_session_on_other_forms_shows_the_portuguese_419_page(): void
    {
        $this->enforceCsrf();
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->from(route('user.vehicles.create'))
            ->post(route('user.vehicles.store'), [])
            ->assertStatus(419)
            ->assertSee('Sua sessão expirou')
            ->assertSee('href="'.route('user.vehicles.create').'"', false)
            ->assertSee('Voltar e recarregar')
            ->assertDontSee('Page Expired');
    }

    public function test_portal_access_rules(): void
    {
        $owner = User::factory()->asUser()->make();
        $admin = User::factory()->asUser()->asAdmin()->make();
        $garage = User::factory()->asGarage()->make();

        $this->assertTrue(PortalAccess::canAccessPath($owner, '/usuario/veiculos/3'));
        $this->assertFalse(PortalAccess::canAccessPath($owner, '/garagem/estoque'));
        $this->assertFalse(PortalAccess::canAccessPath($owner, '/admin'));
        $this->assertTrue(PortalAccess::canAccessPath($admin, '/admin/marcas'));
        $this->assertTrue(PortalAccess::canAccessPath($garage, '/buscar-veiculo?placa=ABC1D23'));
        $this->assertTrue(PortalAccess::canAccessPath($garage, '/notificacoes'));
        $this->assertTrue(PortalAccess::canAccessPath($garage, '/conta'));
        $this->assertFalse(PortalAccess::canAccessPath($garage, '/blog'));
        $this->assertFalse(PortalAccess::canAccessPath($garage, '/garagem-falsa'));
        $this->assertFalse(PortalAccess::allowsIntendedUrl($garage, '//evil.example/garagem', 'localhost'));
        $this->assertTrue(PortalAccess::allowsIntendedUrl($garage, 'http://localhost/garagem/estoque', 'localhost'));
        $this->assertSame('login.admin', PortalAccess::loginRouteName($admin));
        $this->assertSame('Lojista', PortalAccess::accountLabel($garage));
    }

    /**
     * O ValidateCsrfToken não confere o token em testes; esta cópia confere, para simular a
     * sessão expirada (419).
     */
    private function enforceCsrf(): void
    {
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app->make(Encrypter::class)) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
