<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthPagesGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth');
        RateLimiter::clear('auth-web');
    }

    public function test_lojista_login_does_not_offer_owner_signup(): void
    {
        $this->get('/login/lojista')
            ->assertOk()
            ->assertDontSee('Cadastre sua loja')
            ->assertDontSee('href="'.route('register').'"', false)
            ->assertSee('Quer trazer sua loja para a RevisaLog?')
            ->assertSee('href="'.route('contact.show', ['assunto' => 'partnership']).'"', false)
            ->assertSee('Fale com a equipe');
    }

    public function test_oficina_login_points_to_partnership_contact(): void
    {
        $this->get('/login/oficina')
            ->assertOk()
            ->assertDontSee('href="'.route('register').'"', false)
            ->assertSee('Quer trazer sua oficina para a RevisaLog?')
            ->assertSee('href="'.route('contact.show', ['assunto' => 'partnership']).'"', false);
    }

    public function test_usuario_login_keeps_owner_signup_link(): void
    {
        $this->get('/login/usuario')
            ->assertOk()
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('Criar conta grátis');
    }

    public function test_partnership_link_preselects_contact_subject(): void
    {
        $this->get(route('contact.show', ['assunto' => 'partnership']))
            ->assertOk()
            ->assertSee('<option value="partnership" selected', false);
    }

    public function test_login_hub_separates_owner_signup_from_partners(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Proprietário sem conta?')
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('Lojista ou oficina?')
            ->assertSee('href="'.route('contact.show', ['assunto' => 'partnership']).'"', false)
            ->assertSee('Histórico dos seus veículos e manutenções.')
            ->assertDontSee('text-automotive-300', false)
            ->assertDontSee('text-wrench-400', false);
    }

    public function test_login_form_has_autocomplete_hints(): void
    {
        $this->get('/login/usuario')
            ->assertOk()
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_invalid_credentials_show_form_level_alert(): void
    {
        User::factory()->asUser()->create([
            'email' => 'dono@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->from('/login/usuario')
            ->post('/login/usuario', [
                'email' => 'dono@example.com',
                'password' => 'senha-errada',
            ])
            ->assertRedirect('/login/usuario')
            ->assertSessionHasErrors(['credentials' => 'E-mail ou senha incorretos.'])
            ->assertSessionDoesntHaveErrors('email');

        $this->assertGuest();

        $this->get('/login/usuario')
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('E-mail ou senha incorretos.')
            ->assertDontSee('Credenciais inválidas.')
            ->assertDontSee('aria-invalid="true"', false)
            ->assertSee('value="dono@example.com"', false);
    }

    public function test_missing_password_error_is_shown_on_password_field(): void
    {
        $this->from('/login/usuario')
            ->post('/login/usuario', [
                'email' => 'dono@example.com',
                'password' => '',
            ])
            ->assertRedirect('/login/usuario')
            ->assertSessionHasErrors('password');

        $this->get('/login/usuario')
            ->assertOk()
            ->assertSee('id="password-error"', false)
            ->assertSee('aria-describedby="password-error"', false);
    }

    public function test_owner_on_lojista_portal_is_told_which_portal_to_use(): void
    {
        User::factory()->asUser()->create([
            'email' => 'dono@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->from('/login/lojista')
            ->post('/login/lojista', [
                'email' => 'dono@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect('/login/lojista')
            ->assertSessionHasErrors([
                'email' => 'Esta conta é de proprietário de veículo. Entre pela Área do Proprietário.',
            ]);

        $this->assertGuest();

        $this->get('/login/lojista')
            ->assertOk()
            ->assertSee('Esta conta é de proprietário de veículo. Entre pela Área do Proprietário.')
            ->assertSee('href="'.route('login.usuario').'"', false)
            ->assertSee('Ir para a Área do Proprietário')
            ->assertSee('aria-describedby="email-error"', false);
    }

    public function test_garage_on_owner_portal_is_sent_to_lojista_portal(): void
    {
        User::factory()->asGarage()->create([
            'email' => 'loja@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->from('/login/usuario')
            ->post('/login/usuario', [
                'email' => 'loja@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors([
                'email' => 'Esta conta é de lojista. Entre pela Área do Lojista.',
            ]);

        $this->get('/login/usuario')
            ->assertSee('href="'.route('login.lojista').'"', false)
            ->assertSee('Ir para a Área do Lojista');
    }

    public function test_workshop_on_admin_portal_is_sent_to_oficina_portal(): void
    {
        User::factory()->asWorkshop()->create([
            'email' => 'oficina@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->from('/login/admin')
            ->post('/login/admin', [
                'email' => 'oficina@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors([
                'email' => 'Esta conta é de oficina. Entre pela Área da Oficina.',
            ]);

        $this->assertGuest();
    }

    public function test_register_page_is_explicit_about_owner_account_and_legal_terms(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Crie sua conta de proprietário')
            ->assertSee('Conta gratuita para proprietários de veículos.')
            ->assertSee('href="'.route('contact.show', ['assunto' => 'partnership']).'"', false)
            ->assertSee('Ao criar a conta, você aceita os')
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('Termos de uso')
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertSee('Política de privacidade')
            ->assertSee('autocomplete="new-password"', false);
    }

    /**
     * O cadastro fica no card escuro do guest. CPF/CNPJ e telefone são opcionais e não têm validação
     * enquanto a pessoa digita (sem data-mask nem dica reescrita pelo form-ux.js): o erro só aparece
     * depois do envio, no próprio campo. Os critérios da senha usam papéis semânticos, que viram tons
     * claros dentro de .theme-inverse, e as cores padrão de form-ux.js também.
     */
    public function test_register_live_validation_uses_colors_readable_on_the_dark_card(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-field-hint', $html);
        $this->assertStringNotContainsString('data-mask=', $html);
        $this->assertMatchesRegularExpression('/data-password-criteria\s+data-ok-class="text-success"\s+data-idle-class="text-muted-foreground"\s+data-error-class="text-danger"/', $html);
        $this->assertStringContainsString('<body class="theme-inverse', $html);
        $this->assertStringNotContainsString('text-automotive-400', $html);

        $script = file_get_contents(resource_path('js/form-ux.js'));

        $this->assertStringContainsString("stateClasses(hint, 'ok', 'mt-1 text-sm text-success')", $script);
        $this->assertStringContainsString("stateClasses(hint, 'error', 'mt-1 text-sm text-danger')", $script);
        $this->assertStringContainsString("stateClasses(criteria, 'idle', 'text-muted-foreground')", $script);
        $this->assertDoesNotMatchRegularExpression('/text-(?:green|red)-\d{3}|text-automotive-400/', $script);
    }
}
