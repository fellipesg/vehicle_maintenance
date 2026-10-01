<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * "Editar veículo" (Proprietário e Lojista) normaliza placa, chassi e RENAVAM antes de validar,
 * como o cadastro: o unique compara o valor que será gravado, então um identificador já em uso,
 * digitado em minúsculas ou com separadores, volta como erro no campo em vez de estourar o índice
 * único do banco.
 */
class VehicleEditIdentifierUniquenessTest extends TestCase
{
    use InspectsGaragePages;
    use InspectsOwnerPages;
    use RefreshDatabase;

    public function test_owner_gets_a_field_error_for_a_chassis_in_use_typed_in_lowercase(): void
    {
        Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004251']);
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020, 'chassis' => '9BWZZZ377VT009999']);

        $this->actingAs($owner)
            ->from(route('user.vehicles.edit', $vehicle))
            ->put(route('user.vehicles.update', $vehicle), $this->payload($vehicle, ['chassis' => '9bwzzz377vt004251']))
            ->assertRedirect(route('user.vehicles.edit', $vehicle))
            ->assertSessionHasErrors(['chassis']);

        $this->assertSame('9BWZZZ377VT009999', $vehicle->fresh()->chassis);
    }

    public function test_owner_gets_field_errors_for_a_plate_and_renavam_in_use_typed_with_separators(): void
    {
        Vehicle::factory()->create(['license_plate' => 'ABC1D23', 'renavam' => '12345678901']);
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020]);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), $this->payload($vehicle, [
                'license_plate' => 'abc-1d23',
                'renavam' => '1234567890-1',
            ]))
            ->assertSessionHasErrors(['license_plate', 'renavam']);
    }

    public function test_dealer_gets_a_field_error_for_a_chassis_in_use_typed_with_separators(): void
    {
        Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004251']);
        $garage = User::factory()->asGarage()->create();
        $vehicle = $this->stockVehicle($garage, ['year' => 2020, 'chassis' => '9BWZZZ377VT009999']);

        $this->actingAs($garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['chassis' => '9bw zzz 377 vt 004251']))
            ->assertSessionHasErrors(['chassis']);

        $this->assertSame('9BWZZZ377VT009999', $vehicle->fresh()->chassis);
    }

    public function test_identifiers_typed_with_separators_are_saved_normalized(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020, 'license_plate' => 'OLD1A23']);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), $this->payload($vehicle, [
                'license_plate' => 'new-2b34',
                'renavam' => '987.654.321-09',
                'chassis' => '9bw-zzz-377-vt-004252',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $vehicle->refresh();
        $this->assertSame('NEW2B34', $vehicle->license_plate);
        $this->assertSame('98765432109', $vehicle->renavam);
        $this->assertSame('9BWZZZ377VT004252', $vehicle->chassis);
    }

    public function test_keeping_the_vehicle_own_identifiers_is_not_a_duplicate(): void
    {
        $owner = $this->owner();
        $vehicle = $this->ownedVehicle($owner, ['year' => 2020, 'chassis' => '9BWZZZ377VT004253', 'license_plate' => 'KEP1A23']);

        $this->actingAs($owner)
            ->put(route('user.vehicles.update', $vehicle), $this->payload($vehicle, [
                'chassis' => '9bwzzz377vt004253',
                'license_plate' => 'kep-1a23',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('9BWZZZ377VT004253', $vehicle->fresh()->chassis);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'license_plate' => $vehicle->license_plate,
            'renavam' => $vehicle->renavam,
            'crv_number' => $vehicle->crv_number ?? '123456789012',
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'chassis' => $vehicle->chassis,
            'motorization' => $vehicle->motorization,
            'engine' => $vehicle->engine,
        ], $overrides);
    }
}
