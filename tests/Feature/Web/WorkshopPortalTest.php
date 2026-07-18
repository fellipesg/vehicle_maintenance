<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    public function test_workshop_dashboard_is_accessible(): void
    {
        $this->actingAs($this->workshopUser)
            ->get('/oficina/dashboard')
            ->assertOk()
            ->assertSee('Portal da Oficina');
    }

    public function test_workshop_can_register_profile(): void
    {
        $this->actingAs($this->workshopUser)
            ->post('/oficina/perfil', [
                'name' => 'Mecânica do Bairro',
                'phone' => '11999999999',
                'cep' => '01310100',
                'street' => 'Av Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
            ])
            ->assertRedirect(route('workshop.dashboard'));

        $this->assertDatabaseHas('workshops', [
            'name' => 'Mecânica do Bairro',
            'user_id' => $this->workshopUser->id,
        ]);
    }

    public function test_workshop_can_view_profile(): void
    {
        Workshop::factory()->forUser($this->workshopUser)->create(['name' => 'Oficina XYZ']);

        $this->actingAs($this->workshopUser)
            ->get('/oficina/perfil')
            ->assertOk()
            ->assertSee('Oficina XYZ');
    }
}
