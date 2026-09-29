<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Chassi não informado" só vira link quando quem chama passa a rota de edição do próprio portal.
 * Antes o componente caía em user.vehicles.edit para qualquer usuário logado e gerava 403.
 */
class VehicleIdentityEditLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_search_never_links_missing_chassis_to_the_owner_edit_page(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['chassis' => null, 'license_plate' => 'SEM1C23']);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($owner)
            ->get('/buscar-veiculo?identifier=SEM1C23')
            ->assertOk()
            ->assertSee('Chassi não informado')
            ->assertDontSee('/usuario/veiculos/'.$vehicle->id.'/editar', false);
    }

    public function test_garage_vehicle_page_never_links_missing_chassis_to_the_owner_edit_page(): void
    {
        $garage = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create(['chassis' => null]);
        $garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $garage->tenant_id,
        ]);

        $this->actingAs($garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Chassi não informado')
            ->assertDontSee('/usuario/veiculos/'.$vehicle->id.'/editar', false);
    }

    public function test_component_renders_a_plain_chip_without_an_edit_route_even_when_logged_in(): void
    {
        $this->actingAs(User::factory()->asUser()->create());
        $vehicle = Vehicle::factory()->create(['chassis' => null]);

        $this->blade('<x-vehicle-identity :vehicle="$vehicle" size="hero" />', ['vehicle' => $vehicle])
            ->assertSee('Chassi não informado')
            ->assertDontSee('<a ', false);

        $this->blade('<x-vehicle-identity :vehicle="$vehicle" size="hero" :edit-route="null" />', ['vehicle' => $vehicle])
            ->assertDontSee('<a ', false);
    }

    public function test_component_links_missing_chassis_to_the_route_the_caller_passes(): void
    {
        $vehicle = Vehicle::factory()->create(['chassis' => null]);

        $this->blade('<x-vehicle-identity :vehicle="$vehicle" :edit-route="$url" />', [
            'vehicle' => $vehicle,
            'url' => '/usuario/veiculos/'.$vehicle->id.'/editar',
        ])->assertSee('<a href="/usuario/veiculos/'.$vehicle->id.'/editar"', false);
    }
}
