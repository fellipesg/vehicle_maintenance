<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Busca de veículo: a oficina que acha o carro abre a OS dali, com a placa preenchida. Os outros
 * perfis não veem a ação. A busca sem resultado usa <x-ui.empty-state> (tokens, dicas e próximo passo).
 */
class VehicleSearchWorkshopActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_can_open_an_os_for_the_found_vehicle(): void
    {
        Vehicle::factory()->create(['license_plate' => 'QOS6H54']);
        $workshop = User::factory()->asWorkshop()->create();

        $this->actingAs($workshop)
            ->get(route('vehicle.search', ['identifier' => 'QOS6H54']))
            ->assertOk()
            ->assertSee('Abrir OS para este veículo')
            ->assertSee('href="'.e(route('workshop.maintenances.create', ['license_plate' => 'QOS6H54'])).'"', false);
    }

    public function test_other_profiles_do_not_see_the_os_action(): void
    {
        Vehicle::factory()->create(['license_plate' => 'QOS6H54']);

        foreach (['asUser', 'asGarage'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create())
                ->get(route('vehicle.search', ['identifier' => 'QOS6H54']))
                ->assertOk()
                ->assertDontSee('Abrir OS para este veículo');
        }
    }

    public function test_the_os_form_opens_with_the_plate_from_the_search(): void
    {
        Vehicle::factory()->create(['license_plate' => 'QOS6H54']);
        $workshop = User::factory()->asWorkshop()->create();

        $this->actingAs($workshop)
            ->get(route('workshop.maintenances.create', ['license_plate' => 'QOS6H54']))
            ->assertOk()
            ->assertSee('QOS6H54');
    }

    public function test_not_found_is_an_empty_state_with_tokens_instead_of_a_yellow_box(): void
    {
        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'NAO0000']))
            ->assertOk()
            ->assertSee('data-slot="empty-state"', false)
            ->assertSee('Nenhum veículo com “NAO0000”', false)
            ->assertDontSee('bg-yellow-50', false);
    }
}
