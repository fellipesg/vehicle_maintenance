<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\TestCase;

/**
 * Ficha do veículo do Lojista (<x-vehicle.detail>) e "Editar veículo e capas" (garage.vehicles.edit),
 * só para o dono atual do veículo no estoque.
 */
class GarageVehicleEditTest extends TestCase
{
    use InspectsGaragePages;
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_vehicle_page_uses_the_shared_detail_with_edit_and_register_actions(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic', 'year' => 2020, 'color' => 'Prata', 'license_plate' => 'CIV1C23', 'current_kilometers' => 48_500]);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'kilometers' => 40_000, 'maintenance_type' => 'Troca de correia']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.show', $vehicle)));

        $this->assertSingleHeading($xpath, 'Honda Civic');
        $this->assertSame([['Estoque', route('garage.vehicles.index')], ['Honda Civic', null]], $this->garageTrail($xpath));
        $this->assertSame('2020 · Prata · Placa CIV1C23', $this->garageText($this->garageElement($xpath, '//*[@data-slot="page-header-description"]')));

        $actions = $xpath->query('//*[@data-slot="page-header-actions"]//a[@data-slot="button"]');
        $this->assertSame(2, $actions->length);
        $this->assertSame(route('garage.vehicles.edit', $vehicle), $actions->item(0)->getAttribute('href'));
        $this->assertStringContainsString('Editar veículo e capas', $this->garageText($actions->item(0)));
        $this->assertSame(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id]), $actions->item(1)->getAttribute('href'));

        $this->garageElement($xpath, '//*[@data-vehicle-detail][@data-portal="garage"]');
        $this->garageElement($xpath, '//*[@id="linha-do-tempo"]');
        $this->garageElement($xpath, '//*[@id="historico"]');
        $this->garageElement($xpath, '//*[@id="documentos"]');
        $this->assertStringContainsString('48.500 km', $this->garageText($this->garageElement($xpath, '//*[@data-slot="vehicle-detail-summary"]')));
        $this->assertSame(
            route('garage.maintenances.show', $maintenance),
            $this->garageElement($xpath, '//*[@id="manutencao-'.$maintenance->id.'"]//a')->getAttribute('href'),
        );
    }

    public function test_empty_history_offers_to_register_the_first_maintenance(): void
    {
        $vehicle = $this->stockVehicle($this->garage);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.show', $vehicle)));

        $this->assertGreaterThanOrEqual(
            1,
            $xpath->query('//*[@data-slot="empty-state-actions"]//a[@href="'.route('garage.maintenances.create', ['vehicle_id' => $vehicle->id]).'"]')->length,
        );
    }

    public function test_consignment_page_registers_maintenance_but_does_not_edit_the_vehicle(): void
    {
        $vehicle = $this->consignedStockVehicle($this->garage, 'approved');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-consignment-notice', false)
            ->assertSee('Registrar manutenção')
            // Editar o veículo continua sendo só do dono atual.
            ->assertDontSee('Editar veículo e capas')
            ->assertDontSee(route('garage.vehicles.edit', $vehicle), false);
    }

    public function test_vehicle_page_is_forbidden_outside_the_stock(): void
    {
        $foreign = Vehicle::factory()->create();
        $sold = Vehicle::factory()->create();
        $this->garage->vehicles()->attach($sold->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYear(),
            'tenant_id' => $this->garage->tenant_id,
        ]);

        foreach ([$foreign, $sold] as $vehicle) {
            $this->actingAs($this->garage)->get(route('garage.vehicles.show', $vehicle))->assertForbidden();
        }

        // A consignação declarada abre: é nela que a loja registra as manutenções.
        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $this->consignedStockVehicle($this->garage, 'pending')))
            ->assertOk();
    }

    public function test_edit_page_has_the_two_cover_croppers_and_the_vehicle_fields(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'CIV1C23', 'chassis' => '9BWZZZ377VT004277']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.vehicles.edit', $vehicle)));

        $this->assertSingleHeading($xpath, 'Editar veículo');
        $this->assertSame(
            [['Estoque', route('garage.vehicles.index')], ['Honda Civic', route('garage.vehicles.show', $vehicle)], ['Editar', null]],
            $this->garageTrail($xpath),
        );

        $form = $this->garageElement($xpath, '//form[@data-garage-vehicle-form]');
        $this->assertSame(route('garage.vehicles.update', $vehicle), $form->getAttribute('action'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));
        $this->garageElement($xpath, './/input[@name="_method"][@value="PUT"]', $form);

        $this->assertSame('16:9', $this->garageElement($xpath, './/*[@data-image-cropper][.//input[@name="cover"]]', $form)->getAttribute('data-aspect'));
        $this->assertSame('9:16', $this->garageElement($xpath, './/*[@data-image-cropper][.//input[@name="cover_portrait"]]', $form)->getAttribute('data-aspect'));
        $this->garageElement($xpath, './/section[@id="capas"]', $form);

        $this->assertSame('CIV1C23', $this->garageElement($xpath, './/input[@name="license_plate"]', $form)->getAttribute('value'));
        $this->assertSame('9BWZZZ377VT004277', $this->garageElement($xpath, './/input[@name="chassis"]', $form)->getAttribute('value'));
        $this->garageElement($xpath, './/input[@name="current_kilometers"]', $form);
        $this->assertSame(route('garage.vehicles.show', $vehicle), $this->garageElement($xpath, './/*[@data-slot="form-actions"]//a', $form)->getAttribute('href'));
    }

    public function test_update_saves_data_plate_history_and_covers_and_returns_to_the_vehicle(): void
    {
        $this->fakeCoversDisk('r2');
        $vehicle = $this->stockVehicle($this->garage, ['license_plate' => 'OLD1A23', 'year' => 2020, 'crv_number' => '123456789012']);

        $this->actingAs($this->garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, [
                'license_plate' => 'NEW2B34',
                'color' => 'Azul',
                'cover' => UploadedFile::fake()->image('capa-recorte.jpg', 1600, 900),
                'cover_portrait' => UploadedFile::fake()->image('retrato-recorte.jpg', 900, 1600),
            ]))
            ->assertRedirect(route('garage.vehicles.show', $vehicle))
            ->assertSessionHas('success', 'Veículo atualizado.');

        $vehicle->refresh();
        $this->assertSame('NEW2B34', $vehicle->license_plate);
        $this->assertSame('Azul', $vehicle->color);
        $this->assertDatabaseHas('vehicle_plates', ['vehicle_id' => $vehicle->id, 'plate' => 'NEW2B34', 'source' => 'manual']);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_path);
        Storage::disk('r2')->assertExists($vehicle->cover_photo_portrait_path);
    }

    public function test_current_kilometers_cannot_go_below_the_last_maintenance(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['year' => 2020, 'current_kilometers' => 60_000, 'odometer_at_registration' => 50_000]);
        Maintenance::factory()->declaredByGarage()->create(['vehicle_id' => $vehicle->id, 'kilometers' => 55_000, 'user_id' => $this->garage->id]);

        $this->actingAs($this->garage)
            ->from(route('garage.vehicles.edit', $vehicle))
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['current_kilometers' => 54_000]))
            ->assertRedirect(route('garage.vehicles.edit', $vehicle))
            ->assertSessionHasErrors(['current_kilometers' => 'A quilometragem atual não pode ser menor que a da última manutenção registrada (55.000 km).']);

        $this->assertSame(60_000, $vehicle->fresh()->current_kilometers);

        $this->actingAs($this->garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['current_kilometers' => 62_000]))
            ->assertRedirect(route('garage.vehicles.show', $vehicle));

        $this->assertSame(62_000, $vehicle->fresh()->current_kilometers);
        $this->assertSame(50_000, $vehicle->fresh()->odometer_at_registration);
    }

    public function test_typo_in_the_registration_kilometers_is_corrected_with_the_current_kilometers(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['year' => 2020, 'current_kilometers' => 850_000, 'odometer_at_registration' => 850_000]);

        $this->actingAs($this->garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['current_kilometers' => 85_000]))
            ->assertRedirect(route('garage.vehicles.show', $vehicle));

        $this->assertSame(85_000, $vehicle->fresh()->current_kilometers);
        $this->assertSame(85_000, $vehicle->fresh()->odometer_at_registration);
    }

    public function test_chassis_is_required_and_validated(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['year' => 2020]);

        $this->actingAs($this->garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['chassis' => '']))
            ->assertSessionHasErrors('chassis');

        $this->actingAs($this->garage)
            ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['chassis' => 'ABC']))
            ->assertSessionHasErrors('chassis');
    }

    public function test_only_the_current_owner_can_edit(): void
    {
        $approved = $this->consignedStockVehicle($this->garage, 'approved');
        $otherGarage = User::factory()->asGarage()->create();
        $foreign = $this->stockVehicle($otherGarage, ['year' => 2020]);

        foreach ([$approved, $foreign] as $vehicle) {
            $this->actingAs($this->garage)->get(route('garage.vehicles.edit', $vehicle))->assertForbidden();
            $this->actingAs($this->garage)
                ->put(route('garage.vehicles.update', $vehicle), $this->payload($vehicle, ['color' => 'Verde']))
                ->assertForbidden();
            $this->assertNotSame('Verde', $vehicle->fresh()->color);
        }
    }

    public function test_owner_account_cannot_open_the_garage_edit(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->actingAs($owner)
            ->get(route('garage.vehicles.edit', $vehicle))
            ->assertRedirect(route('user.dashboard'));
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
