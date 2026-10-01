<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro sem CRLV-e: proibido no Lojista, sinalizado no Proprietário.
 *
 * Sem o documento ninguém confirma de quem é o veículo. Na loja isso permitiria se declarar dona de
 * um carro de terceiro, então o CRLV-e é obrigatório. No proprietário o cadastro manual continua,
 * mas o veículo carrega o aviso — manutenção declarada sobre posse não confirmada é o que corrói a
 * confiança no histórico.
 */
class VehicleOwnershipVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
    }

    public function test_dealer_document_step_has_no_manual_form(): void
    {
        $this->actingAs(User::factory()->asGarage()->create())
            ->get(route('garage.vehicles.create'))
            ->assertOk()
            ->assertDontSee('data-vehicle-entry-manual', false)
            ->assertSee('data-manual-entry-blocked', false)
            ->assertSee('O CRLV-e é obrigatório no estoque');
    }

    public function test_dealer_cannot_register_a_vehicle_without_the_crlv(): void
    {
        $this->actingAs(User::factory()->asGarage()->create())
            ->from(route('garage.vehicles.create'))
            ->post(route('garage.vehicles.store'), $this->manualPayload())
            ->assertRedirect(route('garage.vehicles.create'))
            ->assertSessionHasErrors('crlv');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_owner_still_registers_manually_and_the_vehicle_is_flagged(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->post(route('user.vehicles.store'), $this->manualPayload())
            ->assertSessionDoesntHaveErrors();

        $vehicle = Vehicle::where('license_plate', 'PHF9J95')->firstOrFail();

        $this->assertFalse($vehicle->hasVerifiedOwnership());
        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'ownership_verified_at' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-ownership-unverified', false)
            ->assertSee('Propriedade não confirmada');
    }

    public function test_vehicle_confirmed_by_the_crlv_has_no_warning(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $owner->tenant_id,
            'ownership_verified_at' => now(),
        ]);

        $this->assertTrue($vehicle->hasVerifiedOwnership());

        $this->actingAs($owner)
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('data-ownership-unverified', false);
    }

    public function test_provenance_lookup_warns_about_an_unconfirmed_vehicle(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->actingAs(User::factory()->asUser()->create())
            ->get(route('vehicle.search', ['identifier' => 'ABC1D23']))
            ->assertOk()
            ->assertSee('data-ownership-unverified', false)
            ->assertSee('Propriedade não confirmada');
    }

    /**
     * @return array<string, mixed>
     */
    private function manualPayload(): array
    {
        return [
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
            'chassis' => '93HFB9640GZ202125',
            'current_kilometers' => 85_000,
            'terms_accepted' => '1',
        ];
    }
}
