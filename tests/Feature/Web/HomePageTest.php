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
            ->assertSee('Para quem é o sistema?')
            ->assertSee('R$ 0')
            ->assertSee('Placa atual')
            ->assertSee('93HFB1640NZ004251')
            ->assertSee('00384719256')
            ->assertSeeInOrder(['40.012 km', '40.580 km', '41.240 km', '42.180 km'])
            ->assertSee('Dono do carro')
            ->assertSee('Linha do tempo permanente')
            ->assertSee('Histórico permanente · Grátis no lançamento')
            ->assertSee('O app de verdade')
            ->assertSee('landing/app-vehicle.png', false)
            ->assertSee('og-preview.png', false)
            ->assertSee('id="preco"', false)
            ->assertSee('id="como-funciona"', false)
            ->assertSee('id="telas"', false)
            ->assertSee('id="recursos"', false)
            ->assertSee('id="app"', false);
    }

    public function test_guest_home_links_logo_to_home_and_shows_landing_anchors(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="#como-funciona"', false)
            ->assertSee('href="#recursos"', false)
            ->assertSee('href="#telas"', false)
            ->assertSee('href="#preco"', false)
            ->assertDontSee('Ir para o painel')
            ->assertDontSee(route('user.dashboard'), false);
    }

    public function test_authenticated_user_sees_dashboard_cta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Ir para o painel')
            ->assertDontSee('Começar grátis');
    }

    public function test_workshop_and_garage_cards_link_to_typed_logins(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login.lojista'), false)
            ->assertSee(route('login.oficina'), false)
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
            ->assertSee('Buscar Histórico');
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
