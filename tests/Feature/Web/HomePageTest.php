<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_accessible(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('RevisaLog')
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('contact.show'), false)
            ->assertSee('suporte@revisalog.com.br')
            ->assertSee('O histórico do carro')
            ->assertSee('Começar grátis')
            ->assertSee('Grátis enquanto a rede cresce')
            ->assertSee('Selo da oficina')
            ->assertSee('Para quem é o RevisaLog')
            ->assertSee('R$ 0')
            ->assertSee('Placa atual')
            ->assertSee('93HFB1640NZ004251')
            ->assertSee('00384719256')
            ->assertSeeInOrder(['40.012 km', '40.580 km', '41.240 km', '42.180 km'])
            ->assertSee('Dono do carro')
            ->assertSee('Linha do tempo do veículo')
            ->assertSee('Histórico que fica no veículo')
            ->assertSee('O mesmo histórico, no seu bolso')
            ->assertSee('landing/app-vehicle.png', false)
            ->assertSee('landing/app-timeline.png', false)
            ->assertSee('og-preview.png', false)
            ->assertSee('id="preco"', false)
            ->assertSee('id="como-funciona"', false)
            ->assertSee('id="produto"', false)
            ->assertSee('id="app"', false)
            ->assertDontSee('id="telas"', false)
            ->assertDontSee('id="recursos"', false);
    }

    public function test_guest_home_links_logo_to_home_and_shows_landing_anchors(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="#como-funciona"', false)
            ->assertSee('href="#procedencia"', false)
            ->assertSee('href="#para-quem"', false)
            ->assertSee('href="#preco"', false)
            ->assertDontSee('Ir para o Início')
            ->assertDontSee(route('user.dashboard'), false);
    }

    public function test_authenticated_user_sees_dashboard_cta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Ir para o Início')
            ->assertSee('href="'.route('user.dashboard').'"', false)
            ->assertDontSee('Ir para o painel')
            ->assertDontSee('Começar grátis');
    }

    /**
     * PUB-01 e PUB-X03: a busca exige conta, então a landing não fala em "Busca pública" nem em
     * "Diretório público"; a conferência do selo (/verificar), aberta a todos, fica em Procedência.
     */
    public function test_landing_names_the_search_and_links_the_seal_lookup(): void
    {
        $response = $this->get('/')->assertOk();

        $response
            ->assertDontSee('Busca pública')
            ->assertDontSee('busca pública')
            ->assertDontSee('Diretório')
            ->assertDontSee('diretório')
            ->assertSee('Busca por placa, chassi ou RENAVAM')
            ->assertSee('Oficinas da rede');

        $html = $response->getContent();
        $procedencia = substr($html, strpos($html, 'id="procedencia"'));
        $procedencia = substr($procedencia, 0, strpos($procedencia, '</section>'));

        $this->assertStringContainsString('href="'.route('verification.lookup').'"', $procedencia);
        $this->assertStringContainsString('Conferir selo da oficina', $procedencia);
    }

    /**
     * PUB-27: lojista e oficina novos entram pelo contato de parceria; quem já tem conta entra pelo
     * login do próprio portal ("Já tenho conta · Entrar", com o perfil só para leitor de tela).
     */
    public function test_workshop_and_garage_cards_link_to_typed_logins(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login.lojista'), false)
            ->assertSee(route('login.oficina'), false)
            ->assertSee('Já tenho conta · Entrar<span class="sr-only"> como lojista</span>', false)
            ->assertSee('Já tenho conta · Entrar<span class="sr-only"> como oficina</span>', false)
            ->assertSee('Entrar como lojista')
            ->assertSee('Entrar como oficina');
    }

    public function test_vehicle_search_requires_authentication(): void
    {
        $this->get('/buscar-veiculo')->assertRedirect(route('login'));
    }

    public function test_vehicle_search_page_is_accessible_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/buscar-veiculo')
            ->assertOk()
            ->assertSee('Buscar histórico de veículo');
    }

    public function test_vehicle_search_finds_vehicle_by_plate(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'cover_photo_path' => 'vehicle-covers/search.jpg',
        ]);

        $this->actingAs($user)
            ->get('/buscar-veiculo?identifier=ABC1D23')
            ->assertOk()
            ->assertSee($vehicle->brand)
            ->assertSee('ABC1D23')
            ->assertSee('Capa do '.$vehicle->brand.' '.$vehicle->model, false);
    }
}
