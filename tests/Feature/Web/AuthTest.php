<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth');
        RateLimiter::clear('auth-web');
    }

    public function test_login_hub_is_accessible(): void
    {
        $this->get('/login')->assertOk()->assertSee('Como você deseja entrar?');
    }

    public function test_typed_login_pages_are_accessible(): void
    {
        $this->get('/login/usuario')->assertOk()->assertSee('Área do Proprietário');
        $this->get('/login/lojista')->assertOk()->assertSee('Área do Lojista');
        $this->get('/login/admin')->assertOk()->assertSee('Painel Administrador');
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Criar conta')
            ->assertSee('Mínimo de 8 caracteres')
            ->assertSee('Confirmação igual à senha');
    }

    public function test_user_can_register_as_common_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'user',
            'document' => '529.982.247-25',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'joao@example.com', 'user_type' => 'user']);

        $user = User::where('email', 'joao@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('52998224725', $user->normalizedDocument());
        $this->assertNotSame('52998224725', $user->getRawOriginal('document'));
    }

    public function test_user_can_register_as_garage(): void
    {
        $response = $this->post('/register', [
            'name' => 'Garagem Central',
            'email' => 'garagem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'garage',
        ]);

        $response->assertSessionHasErrors('user_type');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'garagem@example.com']);
    }

    public function test_user_can_login_via_user_portal(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login/usuario', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_garage_can_login_via_lojista_portal(): void
    {
        $garage = User::factory()->asGarage()->create([
            'email' => 'loja@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login/lojista', [
            'email' => 'loja@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('garage.dashboard'));

        $this->assertAuthenticatedAs($garage);
    }

    public function test_admin_can_login_via_admin_portal(): void
    {
        $admin = User::factory()->asUser()->asAdmin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login/admin', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_portal_rejects_valid_credentials(): void
    {
        User::factory()->asUser()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login/lojista', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->asUser()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guest_cannot_access_user_dashboard(): void
    {
        $this->get('/usuario/dashboard')->assertRedirect('/login');
    }

    public function test_wrong_user_type_cannot_access_portal(): void
    {
        $garage = User::factory()->asGarage()->create();

        $this->actingAs($garage)
            ->get('/usuario/dashboard')
            ->assertForbidden();
    }

    public function test_web_login_rate_limit_returns_redirect_not_json(): void
    {
        User::factory()->asUser()->create([
            'email' => 'web-ratelimit@example.com',
            'password' => bcrypt('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login/usuario')
                ->post('/login/usuario', [
                    'email' => 'web-ratelimit@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login/usuario');
        }

        $response = $this->from('/login/usuario')
            ->post('/login/usuario', [
                'email' => 'web-ratelimit@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect('/login/usuario')
            ->assertSessionHasErrors('email')
            ->assertSessionHas('errors');

        $this->assertStringNotContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type')
        );
    }

    public function test_web_register_rate_limit_returns_redirect_not_json(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/register')
                ->post('/register', [
                    'name' => 'Rate Limit User',
                    'email' => "register-{$attempt}@example.com",
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'user_type' => 'user',
                    'document' => '529.982.247-25',
                ])
                ->assertRedirect();
        }

        $response = $this->from('/register')
            ->post('/register', [
                'name' => 'Rate Limit User',
                'email' => 'register-blocked@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'user_type' => 'user',
                'document' => '529.982.247-25',
            ]);

        $response->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type')
        );
    }
}
