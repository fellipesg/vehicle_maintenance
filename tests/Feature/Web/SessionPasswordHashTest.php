<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sessão web presa ao hash da senha (App\Http\Middleware\AuthenticateWebSession no grupo web +
 * App\Listeners\StorePasswordHashOnLogin): trocar a senha em outro aparelho desconecta esta sessão,
 * mesmo que ela só tenha feito o login. O formato do hash é o que a Sanctum compara nas chamadas do
 * portal à API com a sessão, então página web e API convivem na mesma sessão.
 */
class SessionPasswordHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_group_authenticates_the_session_against_the_password(): void
    {
        $this->assertContains(AuthenticateWebSession::class, $this->app['router']->getMiddlewareGroups()['web']);
    }

    public function test_login_stores_the_password_hash_in_the_session(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'dono@example.com', 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));

        $this->assertSame($user->getAuthPassword(), session('password_hash_web'));
    }

    public function test_sign_up_stores_the_password_hash_in_the_session(): void
    {
        $this->post('/register', [
            'name' => 'Ana Lima',
            'email' => 'ana@example.com',
            'password' => 'senha-segura-1',
            'password_confirmation' => 'senha-segura-1',
        ])->assertRedirect(route('user.dashboard'));

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame($user->getAuthPassword(), session('password_hash_web'));
    }

    public function test_a_session_that_only_signed_in_keeps_working_while_the_password_is_the_same(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $device = $this->signIn('dono@example.com');

        $this->withSession($device)->get(route('user.dashboard'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_a_session_that_only_signed_in_is_signed_out_when_the_password_changes_elsewhere(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $device = $this->signIn('dono@example.com');

        $user->forceFill(['password' => 'outra-senha-123'])->save();

        $this->withSession($device)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    public function test_portal_api_calls_and_web_pages_share_the_session(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'dono@example.com', 'password' => 'password']);

        $this->get(route('user.dashboard'))->assertOk();
        $this->portalApiCall()->assertOk();
        $this->get(route('user.dashboard'))->assertOk();
        $this->portalApiCall()->assertOk();
        $this->assertAuthenticated();
    }

    public function test_portal_api_call_is_rejected_after_the_password_changes_elsewhere(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $device = $this->signIn('dono@example.com');

        $user->forceFill(['password' => 'outra-senha-123'])->save();

        $this->withSession($device);
        $this->portalApiCall()->assertUnauthorized();
    }

    private function portalApiCall(): TestResponse
    {
        return $this->withHeader('Referer', url('/usuario/veiculos'))->getJson('/api/v1/my-vehicles');
    }

    /**
     * @return array<string, mixed>
     */
    private function signIn(string $email): array
    {
        $this->post(route('login.submit', 'usuario'), ['email' => $email, 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));

        $session = session()->all();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $session;
    }
}
