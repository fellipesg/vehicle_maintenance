<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_accessible(): void
    {
        $this->get('/')->assertOk()->assertSee('Vehicle Maintenance');
    }

    public function test_vehicle_search_page_is_accessible(): void
    {
        $this->get('/buscar-veiculo')->assertOk()->assertSee('Buscar Histórico');
    }

    public function test_vehicle_search_finds_vehicle_by_plate(): void
    {
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'cover_photo_path' => 'vehicle-covers/search.jpg',
        ]);

        $this->get('/buscar-veiculo?identifier=ABC1D23')
            ->assertOk()
            ->assertSee($vehicle->brand)
            ->assertSee('ABC1D23')
            ->assertSee('Capa do '.$vehicle->brand.' '.$vehicle->model, false);
    }
}
