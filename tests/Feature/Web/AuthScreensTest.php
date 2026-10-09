<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\AuthController;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Telas de entrada no template de página: uma view de login para os 4 portais, hub com os 3
 * perfis públicos, cadastro com os campos opcionais recolhidos e os avisos de url.intended.
 */
class AuthScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-web');
    }

    /**
     * @return array<string, array{string, string, string, string|null}>
     */
    public static function loginPortals(): array
    {
        return [
            'proprietário' => ['usuario', 'Área do Proprietário', 'Histórico dos seus veículos e manutenções.', 'Criar conta grátis'],
            'lojista' => ['lojista', 'Área do Lojista', 'Estoque, consignações e manutenções da sua loja.', 'Fale com a equipe'],
            'oficina' => ['oficina', 'Área da Oficina', 'Ordens de serviço com o Selo da oficina e o perfil da sua oficina.', 'Cadastre sua oficina'],
            'admin' => ['admin', 'Painel Administrador', 'Acesso exclusivo para a gestão da plataforma.', null],
        ];
    }

    #[DataProvider('loginPortals')]
    public function test_every_login_url_renders_the_shared_login_view_with_one_heading(string $portal, string $title, string $description, ?string $footerLink): void
    {
        $response = $this->get(route("login.{$portal}"))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee("<title>{$title} · RevisaLog</title>", false);
        $page = $this->page($response);

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame($title, $this->text($this->element($page, 'h1[data-slot="page-header-title"]')));
        $this->assertSame($description, $this->text($this->element($page, '[data-slot="page-header-description"]')));
        $this->assertSame(route('login.submit', $portal), $this->element($page, 'form[method="POST"]')->getAttribute('action'));
        $this->assertSame(route('login'), $this->element($page, '[data-slot="auth-header"] ~ div a[href="'.route('login').'"]')->getAttribute('href'));
        $this->assertNotNull($page->querySelector('[data-slot="auth-header"] svg[data-slot="icon"]'));

        if ($footerLink === null) {
            $this->assertNull($page->querySelector('a[href="'.route('register').'"]'));
            $this->assertStringNotContainsString('Fale com a equipe', $response->getContent());
        } else {
            $this->assertStringContainsString($footerLink, $response->getContent());
        }
    }

    public function test_old_per_portal_login_views_are_gone(): void
    {
        foreach (['auth.login-usuario', 'auth.login-lojista', 'auth.login-oficina', 'auth.login-admin', 'auth.partials.portal-icon'] as $view) {
            $this->assertFalse(view()->exists($view), $view);
        }
    }

    public function test_hub_lists_only_the_three_public_profiles(): void
    {
        $response = $this->get(route('login'))->assertOk();
        $page = $this->page($response);

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Entrar', $this->text($this->element($page, 'h1')));

        $options = $page->querySelectorAll('ul[aria-label="Tipos de conta"] > li[data-slot="auth-portal-option"] > a');
        $this->assertSame(
            [route('login.usuario'), route('login.lojista'), route('login.oficina')],
            array_map(fn (Element $link): string => $link->getAttribute('href'), iterator_to_array($options)),
        );
        $this->assertNull($page->querySelector('a[href="'.route('login.admin').'"]'));
        $this->assertStringNotContainsString('Administrador', $response->getContent());
        $this->assertNull($page->querySelector('[data-highlighted]'));
        $this->assertNull($page->querySelector('[data-slot="alert"]'));
    }

    public function test_hub_explains_that_vehicle_search_needs_an_owner_account(): void
    {
        $this->get(route('vehicle.search'))->assertRedirect(route('login'));

        $page = $this->page($this->get(route('login'))->assertOk());

        $alert = $this->element($page, '[data-slot="alert"]');
        $this->assertStringContainsString('A busca de veículo exige uma conta.', $this->text($alert));
        $this->assertStringContainsString('crie sua conta grátis de proprietário', $this->text($alert));

        $highlighted = $this->element($page, 'li[data-highlighted="true"]');
        $this->assertSame(route('login.usuario'), $this->element($page, 'li[data-highlighted="true"] > a')->getAttribute('href'));
        $this->assertStringContainsString('Indicado para você', $this->text($highlighted));
        $this->assertCount(1, $page->querySelectorAll('li[data-highlighted]'));

        $signup = $this->element($page, 'a[data-slot="button"][href="'.route('register').'"]');
        $this->assertSame('Criar conta grátis', $this->text($signup));
    }

    public function test_hub_asks_to_sign_in_to_continue_for_other_protected_pages(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));

        $page = $this->page($this->get(route('login'))->assertOk());

        $this->assertSame('Informação: Entre para continuar. Essa página exige uma conta.', $this->text($this->element($page, '[data-slot="alert"]')));
        $this->assertNull($page->querySelector('[data-highlighted]'));
    }

    public function test_register_page_uses_the_page_header_and_keeps_optional_fields_collapsed(): void
    {
        $response = $this->get(route('register'))->assertOk()->assertSee('<title>Crie sua conta de proprietário · RevisaLog</title>', false);
        $page = $this->page($response);

        $this->assertCount(1, $page->querySelectorAll('h1'));
        $this->assertSame('Crie sua conta de proprietário', $this->text($this->element($page, 'h1')));

        $optional = $this->element($page, 'details[data-slot="register-optional-fields"]');
        $this->assertFalse($optional->hasAttribute('open'));
        $this->assertStringContainsString('Adicionar CPF e telefone (opcional)', $this->text($this->element($page, 'details > summary')));

        $phone = $this->element($page, 'details input#phone');
        $this->assertSame('tel', $phone->getAttribute('type'));
        $this->assertSame('tel-national', $phone->getAttribute('autocomplete'));
        $this->assertSame('tel', $phone->getAttribute('inputmode'));
        $this->assertSame('15', $phone->getAttribute('maxlength'));
        $this->assertSame(['phone-hint'], explode(' ', $phone->getAttribute('aria-describedby')));

        $document = $this->element($page, 'details input#document');
        $this->assertSame('18', $document->getAttribute('maxlength'));
        $this->assertFalse($document->hasAttribute('data-mask'));

        $this->assertNull($page->querySelector('[data-email-taken]'));
    }

    public function test_register_opens_the_optional_fields_when_one_of_them_has_an_error(): void
    {
        $this->from(route('register'))->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1199',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('phone');

        $page = $this->page($this->get(route('register')));

        $this->assertTrue($this->element($page, 'details[data-slot="register-optional-fields"]')->hasAttribute('open'));
        $this->assertSame('true', $this->element($page, 'input#phone')->getAttribute('aria-invalid'));
        $this->assertSame('1199', $this->element($page, 'input#phone')->getAttribute('value'));
    }

    public function test_pasted_phone_with_mask_is_kept_whole(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '(11) 99999-9999',
            'document' => '529.982.247-25',
        ])->assertRedirect(route('user.dashboard'));

        $this->assertSame('11999999999', User::where('email', 'ana@example.com')->value('phone'));
    }

    public function test_taken_email_offers_sign_in_and_password_reset(): void
    {
        User::factory()->asGarage()->create(['email' => 'loja@example.com']);

        $this->from(route('register'))->post('/register', [
            'name' => 'Loja',
            'email' => 'loja@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);

        $page = $this->page($this->get(route('register')));
        $notice = $this->element($page, '[data-email-taken]');

        $this->assertStringContainsString('Este e-mail já tem conta na RevisaLog.', $this->text($notice));
        $this->assertNotNull($page->querySelector('[data-email-taken] a[href="'.route('login').'"]'));
        $this->assertNotNull($page->querySelector('[data-email-taken] a[href="'.e(route('password.request', ['portal' => 'usuario'])).'"]'));
        $this->assertSame('loja@example.com', $this->element($page, 'input#email')->getAttribute('value'));
    }

    public function test_other_email_errors_do_not_show_the_sign_in_notice(): void
    {
        $this->from(route('register'))->post('/register', [
            'name' => 'Ana',
            'email' => 'nao-e-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertNull($this->page($this->get(route('register')))->querySelector('[data-email-taken]'));
    }

    public function test_register_confirms_the_account_and_shows_the_email_it_was_created_for(): void
    {
        Mail::fake();
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('success', 'Conta criada. Enviamos as boas-vindas para ana@example.com. Próximo passo: adicione seu veículo.');
    }

    public function test_register_returns_to_the_vehicle_search_that_asked_for_an_account(): void
    {
        Mail::fake();
        Notification::fake();

        $this->get(route('vehicle.search', ['placa' => 'ABC1D23']))->assertRedirect(route('login'));

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Depois do cadastro, levamos você direto para a busca de veículo.');

        $this->post('/register', [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('vehicle.search', ['placa' => 'ABC1D23']));

        $this->assertAuthenticated();
    }

    public function test_register_ignores_an_intended_page_of_another_profile(): void
    {
        Mail::fake();
        Notification::fake();

        $this->get('/garagem/estoque')->assertRedirect(route('login.lojista'));

        $this->post('/register', [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('user.dashboard'));
    }

    public function test_logout_says_the_session_was_closed(): void
    {
        $this->actingAs(User::factory()->asUser()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', AuthController::LOGGED_OUT_MESSAGE);

        $this->assertGuest();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee('Você saiu da sua conta.');
    }

    public function test_forgot_and_reset_password_pages_use_the_same_header(): void
    {
        foreach ([route('password.request'), route('password.reset', ['token' => 'abc'])] as $url) {
            $page = $this->page($this->get($url)->assertOk());

            $this->assertCount(1, $page->querySelectorAll('h1'));
            $this->assertNotNull($page->querySelector('[data-slot="auth-header"] h1[data-slot="page-header-title"]'));
        }
    }

    private function page(TestResponse $response): HTMLDocument
    {
        // Prepend a space so no 2-byte UTF-8 sequence lands at offset 4095 (the last byte of
        // lexbor's 4096-byte buffer), which is a PHP 8.4 Dom parser bug that silently drops the
        // leading byte of any such sequence that straddles the boundary.
        $content = ' '.(string) $response->getContent();

        return HTMLDocument::createFromString($content, LIBXML_NOERROR);
    }

    private function element(HTMLDocument $page, string $selector): Element
    {
        $element = $page->querySelector($selector);

        $this->assertInstanceOf(Element::class, $element, "Nenhum elemento casa com {$selector}.");

        return $element;
    }

    private function text(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }
}
