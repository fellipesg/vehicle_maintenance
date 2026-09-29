<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Páginas de erro próprias (resources/views/errors): pt-BR, marca, uma saída para o Início da conta
 * (ou para o site) e contato, no lugar das páginas em inglês do framework.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_shows_portuguese_404_with_way_back_and_contact(): void
    {
        $response = $this->get('/pagina-que-nao-existe')->assertNotFound();

        $response
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<title>Página não encontrada', false)
            ->assertSee('<main id="conteudo" tabindex="-1"', false)
            ->assertSee('Erro 404')
            ->assertSee('Página não encontrada')
            ->assertSee('lockup-horizontal-tagline.png', false)
            ->assertSee('alt="RevisaLog"', false)
            ->assertSee('Voltar ao site')
            ->assertSee('href="'.url('/').'"', false)
            ->assertSee('Fale com a gente')
            ->assertSee('href="'.route('contact.show').'"', false)
            ->assertDontSee('Not Found');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_missing_record_inside_portal_offers_the_account_home(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        $this->actingAs($workshop)
            ->get('/oficina/manutencoes/999999')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertSee('Ir para o Início')
            ->assertSee('href="'.route('workshop.dashboard').'"', false);
    }

    public function test_blog_post_that_does_not_exist_uses_the_portuguese_404(): void
    {
        $this->get('/blog/artigo-inexistente')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertDontSee('Not Found');
    }

    public function test_too_many_requests_shows_portuguese_429_with_wait_time(): void
    {
        RateLimiter::clear('contact|127.0.0.1');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('contact.store'), []);
        }

        $this->post(route('contact.store'), [])
            ->assertStatus(429)
            ->assertSee('Muitas tentativas seguidas')
            ->assertSee('Tente de novo em 1 minuto.')
            ->assertDontSee('Too Many Requests');
    }

    public function test_server_error_shows_portuguese_500_without_account_lookup(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_teste/erro-interno', fn () => throw new RuntimeException('falha de teste'));

        $this->actingAs(User::factory()->asGarage()->create())
            ->get('/_teste/erro-interno')
            ->assertStatus(500)
            ->assertSee('Algo deu errado do nosso lado')
            ->assertSee('Voltar ao site')
            ->assertDontSee('Ir para o Início')
            ->assertDontSee('falha de teste')
            ->assertDontSee('Server Error');
    }

    public function test_maintenance_page_is_in_portuguese(): void
    {
        Route::middleware('web')->get('/_teste/manutencao', fn () => abort(503));

        $this->get('/_teste/manutencao')
            ->assertStatus(503)
            ->assertSee('Estamos em manutenção')
            ->assertSee('A RevisaLog volta em alguns minutos.')
            ->assertDontSee('Service Unavailable');
    }

    public function test_forbidden_page_does_not_show_the_exception_message(): void
    {
        Route::middleware('web')->get('/_teste/proibido', fn () => abort(403, 'This action is unauthorized.'));

        $this->get('/_teste/proibido')
            ->assertForbidden()
            ->assertSee('Você não tem acesso a esta página')
            ->assertDontSee('This action is unauthorized.');
    }

    public function test_session_expired_page_without_referer_falls_back_to_the_default_actions(): void
    {
        Route::middleware('web')->get('/_teste/sessao', fn () => abort(419));

        $this->get('/_teste/sessao')
            ->assertStatus(419)
            ->assertSee('Sua sessão expirou')
            ->assertSee('Voltar ao site')
            ->assertDontSee('Voltar e recarregar')
            ->assertDontSee('Page Expired');
    }

    public function test_session_expired_page_ignores_referer_from_another_site(): void
    {
        Route::middleware('web')->get('/_teste/sessao', fn () => abort(419));

        $this->get('/_teste/sessao', ['referer' => 'https://evil.example/formulario'])
            ->assertStatus(419)
            ->assertDontSee('evil.example');
    }
}
