<?php

namespace Tests\Feature\Web\Workshop;

use App\Models\Vehicle;
use App\Models\VehiclePlate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Workshop\Concerns\InspectsWorkshopPages;
use Tests\TestCase;

/**
 * A etapa 1 da nova OS busca veículo por placa, e qualquer conta de oficina (autocadastro) pode
 * usá-la: ela é limitada pelo balde `search` e, sem vínculo com o veículo, mostra só o que a
 * consulta pública mostra — nunca a placa atual nem o hodômetro
 * (App\Support\Workshop\WorkshopVehicleVisibility, .ai/rules/public-lookup.md).
 */
class WorkshopVehicleLookupPrivacyTest extends TestCase
{
    use InspectsWorkshopPages;
    use RefreshDatabase;

    public function test_workshop_without_a_record_on_the_vehicle_sees_neither_the_kilometers_nor_a_plate_it_did_not_type(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('XYZ9A88', ['current_kilometers' => 123456]);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'xyz9a88']));
        $xpath = $this->page($response);

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        // Marca, modelo, ano e cor são o que a consulta pública já mostra.
        $this->assertStringContainsString('Fiat Argo', $this->text($card));
        $this->assertStringContainsString('2021', $this->text($card));
        // A placa de volta é a digitada; o hodômetro não aparece em lugar nenhum da página.
        $this->assertStringContainsString('XYZ9A88', $this->text($card));
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-vehicle-kilometers]'));
        $response->assertDontSee('123.456');
        $response->assertDontSee('123456', false);

        // O formulário continua abrindo: a oficina digita o hodômetro que leu no painel.
        $this->assertSame(1, $this->countNodes($xpath, '//form[@data-maintenance-os-form]'));
        $this->assertSame('', $this->element($xpath, '//input[@name="kilometers"]')->getAttribute('value'));
        $this->assertStringNotContainsString(
            'Última quilometragem registrada do veículo',
            $this->text($this->element($xpath, '//fieldset[@id="secao-servico"]')),
        );
    }

    public function test_old_plate_never_reveals_the_current_plate_to_a_workshop_without_a_record(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('NEW4C44');
        VehiclePlate::factory()->ended()->create(['vehicle_id' => $vehicle->id, 'plate' => 'OLD1B11']);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'OLD1B11']));
        $xpath = $this->page($response);

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        $this->assertStringContainsString('A placa OLD1B11 é antiga: o veículo usa outra placa hoje.', $this->text($card));
        // Nem no texto, nem no campo oculto que o store recebe.
        $response->assertDontSee('NEW4C44');
        $this->assertSame(
            'OLD1B11',
            $this->element($xpath, '//form[@data-maintenance-os-form]//input[@type="hidden"][@name="license_plate"]')->getAttribute('value'),
        );
    }

    public function test_the_os_still_goes_through_with_the_old_plate_the_workshop_typed(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('NEW4C45', ['current_kilometers' => 50000]);
        VehiclePlate::factory()->ended()->create(['vehicle_id' => $vehicle->id, 'plate' => 'OLD1B12']);

        $this->actingAs($user)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'OLD1B12',
                'maintenance_type' => 'Troca de óleo',
                'maintenance_date' => now()->toDateString(),
                'kilometers' => 60000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $user->workshop->id,
            'kilometers' => 60000,
        ]);
    }

    public function test_workshop_with_a_record_on_the_vehicle_keeps_seeing_the_plate_and_the_kilometers(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('LNK7D77', ['current_kilometers' => 98000]);
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'LNK7D77'])));

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        $this->assertStringContainsString('LNK7D77', $this->text($card));
        $this->assertStringContainsString('Última quilometragem registrada: 98.000 km', $this->text($card));
        $this->assertSame('98000', $this->element($xpath, '//input[@name="kilometers"]')->getAttribute('value'));
    }

    /**
     * Carro sem dono vinculado continua sendo o carro de alguém, e essa população cresce justamente
     * porque oficinas registram OS de carro não reivindicado: uma placa não prova nada sobre ele.
     */
    public function test_vehicle_without_an_owner_found_by_plate_is_masked_too(): void
    {
        $user = $this->workshopUser();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'FRE3E33', 'current_kilometers' => 77000]);
        VehiclePlate::factory()->ended()->create(['vehicle_id' => $vehicle->id, 'plate' => 'OLD1B13']);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'OLD1B13']));
        $xpath = $this->page($response);

        // Nem a placa atual a partir da antiga, nem o hodômetro, nem o valor inicial do campo.
        $response->assertDontSee('FRE3E33');
        $response->assertDontSee('77.000');
        $this->assertSame(0, $this->countNodes($xpath, '//*[@data-vehicle-kilometers]'));
        $this->assertSame('', $this->element($xpath, '//input[@name="kilometers"]')->getAttribute('value'));

        // E o fluxo legítimo continua: o formulário abre com a placa que a oficina digitou.
        $this->assertSame(1, $this->countNodes($xpath, '//form[@data-maintenance-os-form]'));
        $this->assertSame(
            'OLD1B13',
            $this->element($xpath, '//form[@data-maintenance-os-form]//input[@type="hidden"][@name="license_plate"]')->getAttribute('value'),
        );
    }

    /**
     * O que libera o card sem vínculo é a posse: os 17 caracteres exatos do chassi são o carro ou o
     * documento na mão, e é o fluxo em que a oficina cadastra o veículo.
     */
    public function test_vehicle_without_an_owner_found_by_the_full_chassis_shows_everything(): void
    {
        $user = $this->workshopUser();
        Vehicle::factory()->create([
            'license_plate' => 'FRE3E34',
            'chassis' => '9BWZZZ377VT004252',
            'current_kilometers' => 77000,
        ]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['chassis' => '9BWZZZ377VT004252'])));

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        $this->assertStringContainsString('FRE3E34', $this->text($card));
        $this->assertStringContainsString('Última quilometragem registrada: 77.000 km', $this->text($card));
        $this->assertSame('77000', $this->element($xpath, '//input[@name="kilometers"]')->getAttribute('value'));
    }

    /**
     * A oficina que cadastrou o carro pelo chassi volta depois pela placa e continua vendo tudo: o
     * vínculo é a OS que ela mesma registrou, não o chassi digitado de novo.
     */
    public function test_the_workshop_that_registered_the_ownerless_vehicle_still_sees_it_by_plate(): void
    {
        $user = $this->workshopUser();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'FRE3E35', 'current_kilometers' => 42000]);
        $this->sealedOrder($user, ['vehicle_id' => $vehicle->id]);

        $xpath = $this->page($this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'FRE3E35'])));

        $card = $this->element($xpath, '//*[@data-vehicle-found]');
        $this->assertStringContainsString('FRE3E35', $this->text($card));
        $this->assertStringContainsString('Última quilometragem registrada: 42.000 km', $this->text($card));
    }

    public function test_the_workshop_that_only_cited_another_workshops_record_has_no_link(): void
    {
        $user = $this->workshopUser();
        $other = $this->workshopUser();
        $vehicle = $this->ownedVehicle('OTH8F88', ['current_kilometers' => 31000]);
        $this->sealedOrder($other, ['vehicle_id' => $vehicle->id]);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['license_plate' => 'OTH8F88']));

        $response->assertDontSee('31.000');
        $this->assertSame(0, $this->countNodes($this->page($response), '//*[@data-vehicle-kilometers]'));
    }

    public function test_plate_enumeration_is_throttled(): void
    {
        $user = $this->workshopUser();
        $this->ownedVehicle('THR0G00');

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->actingAs($user)
                ->get(route('workshop.maintenances.create', ['license_plate' => 'THR0G'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT)]))
                ->assertOk();
        }

        $this->actingAs($user)
            ->get(route('workshop.maintenances.create', ['license_plate' => 'THR0G00']))
            ->assertStatus(429);
    }

    public function test_the_chassis_lookup_of_an_owned_vehicle_still_says_nothing_about_the_car(): void
    {
        $user = $this->workshopUser();
        $vehicle = $this->ownedVehicle('CHS1H11', ['chassis' => '9BWZZZ377VT004251', 'current_kilometers' => 64000]);

        $response = $this->actingAs($user)->get(route('workshop.maintenances.create', ['chassis' => $vehicle->chassis]));

        $response->assertSee('Este chassi já tem proprietário no RevisaLog');
        $response->assertDontSee('CHS1H11');
        $response->assertDontSee('64.000');
    }
}
