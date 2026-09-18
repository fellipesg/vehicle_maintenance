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
        $this->get('/')->assertOk()->assertSee('Vehicle Maintenance');
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
