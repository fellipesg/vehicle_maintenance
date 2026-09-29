<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsGaragePages;
use Tests\TestCase;

/**
 * "Registrar manutenção" do Lojista: aviso de procedência (Declarada pelo lojista, marcador LJ),
 * seções numeradas em x-ui.form-section, categoria em select sem valor padrão, campo Oficina com as
 * oficinas da rede, e Salvar/Cancelar voltando à ficha quando a ação começou nela.
 */
class GarageMaintenanceFormTest extends TestCase
{
    use InspectsGaragePages;
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
        config(['maintenance.auto_verify_linked_workshop' => false]);
    }

    public function test_form_warns_it_will_be_declared_and_has_no_default_category(): void
    {
        $this->stockVehicle($this->garage);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create')));

        $this->assertSingleHeading($xpath, 'Registrar manutenção');
        $notice = $this->garageElement($xpath, '//*[@data-provenance-notice="declared"]');
        $this->assertStringContainsString('Aparecerá como Declarada pelo lojista', $this->garageText($notice));
        $this->assertStringContainsString('O Selo da oficina só é aplicado quando a oficina da rede registra o serviço no portal dela.', $this->garageText($notice));
        $this->assertSame('LJ', $this->garageText($this->garageElement($xpath, './/*[contains(@class, "prov-marker")][@aria-hidden="true"]', $notice)));

        // Mesmas seções numeradas, rótulos e controles do Proprietário e da Nova OS.
        $this->assertSame(
            ['Etapa 1: Veículo e data', 'Etapa 2: Serviço', 'Etapa 3: Oficina', 'Etapa 4: Notas fiscais'],
            array_map(fn (\DOMElement $legend): string => trim((string) preg_replace('/^\d+\s*/', '', $this->garageText($legend))), iterator_to_array($xpath->query('//form[@data-garage-maintenance-form]//fieldset[@data-slot="form-section"]/legend'))),
        );
        $this->assertStringStartsWith('Serviço realizado', $this->garageText($this->garageElement($xpath, '//label[@for="maintenance_type"]')));
        $category = $this->garageElement($xpath, '//select[@name="service_category"]');
        $this->assertTrue($category->hasAttribute('required'));
        $this->assertSame(8, $xpath->query('.//option', $category)->length, 'Placeholder e as 7 categorias.');
        $this->assertSame(0, $xpath->query('.//option[@selected][@value!=""]', $category)->length, 'Nenhuma categoria escolhida por padrão.');
        $this->assertSame(0, $xpath->query('//input[@type="radio"][@name="service_category"]')->length);
        $this->garageElement($xpath, '//input[@type="checkbox"][@name="is_manufacturer_required"]');
        $this->assertSame(0, $xpath->query('//*[@role="switch"]')->length);

        foreach (['vehicle_id', 'maintenance_date', 'kilometers', 'maintenance_type'] as $field) {
            $this->assertTrue($this->garageElement($xpath, '//*[@name="'.$field.'"]')->hasAttribute('required'), "{$field} é obrigatório.");
        }

        $this->garageElement($xpath, '//input[@type="file"][@name="invoices[]"][@multiple]');
        $this->assertSame(0, $xpath->query('//input[@name="return_to"]')->length);
        $this->assertSame(route('garage.maintenances.index'), $this->garageElement($xpath, '//a[@data-cancel]')->getAttribute('href'));
        $this->assertSame([['Manutenções', route('garage.maintenances.index')], ['Registrar manutenção', null]], $this->garageTrail($xpath));
    }

    public function test_auto_verify_environment_explains_that_the_workshop_seal_is_applied(): void
    {
        config(['maintenance.auto_verify_linked_workshop' => true]);
        $this->stockVehicle($this->garage);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.create'))
            ->assertOk()
            ->assertSee('data-provenance-notice="auto-verify"', false)
            ->assertDontSee('data-provenance-notice="declared"', false);
    }

    public function test_workshop_field_suggests_network_workshops(): void
    {
        $this->stockVehicle($this->garage);
        $workshop = Workshop::factory()->create(['name' => 'Mecânica Boa Vista', 'city' => 'Campinas']);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create')));

        $input = $this->garageElement($xpath, '//input[@name="workshop_name"]');
        $this->assertSame('oficinas-da-rede', $input->getAttribute('list'));
        $this->assertStringContainsString('workshop_name-hint', $input->getAttribute('aria-describedby'));
        $this->assertSame((string) $workshop->id, $this->garageElement($xpath, '//datalist[@id="oficinas-da-rede"]/option[@value="Mecânica Boa Vista · Campinas"]')->getAttribute('data-workshop-id'));
        $this->garageElement($xpath, '//input[@type="hidden"][@name="workshop_id"]');
        $this->assertSame(0, $xpath->query('//select[@name="workshop_id"]')->length);
    }

    public function test_coming_from_the_vehicle_preselects_it_and_returns_to_it(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['brand' => 'Honda', 'model' => 'Civic']);
        $this->stockVehicle($this->garage);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])));

        $this->assertSame((string) $vehicle->id, $this->garageElement($xpath, '//select[@name="vehicle_id"]/option[@selected]')->getAttribute('value'));
        $this->assertSame('vehicle', $this->garageElement($xpath, '//input[@type="hidden"][@name="return_to"]')->getAttribute('value'));
        $this->assertSame(route('garage.vehicles.show', $vehicle), $this->garageElement($xpath, '//a[@data-cancel]')->getAttribute('href'));
        $this->assertSame(
            [['Estoque', route('garage.vehicles.index')], ['Honda Civic', route('garage.vehicles.show', $vehicle)], ['Registrar manutenção', null]],
            $this->garageTrail($xpath),
        );
    }

    public function test_vehicle_outside_the_owned_stock_is_not_preselected(): void
    {
        $this->stockVehicle($this->garage);
        $consigned = $this->consignedStockVehicle($this->garage, 'approved');
        $foreign = Vehicle::factory()->create();

        foreach ([$consigned, $foreign] as $vehicle) {
            $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])));

            $this->assertSame(0, $xpath->query('//select[@name="vehicle_id"]/option[@selected][@value!=""]')->length);
            $this->assertSame(0, $xpath->query('//input[@name="return_to"]')->length);
        }
    }

    public function test_empty_stock_replaces_the_form_with_the_add_to_stock_action(): void
    {
        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create')));

        $this->assertSame(0, $xpath->query('//form[@data-garage-maintenance-form]')->length);
        $this->garageElement($xpath, '//*[@data-maintenance-form-empty]');
        $this->assertSame(
            route('garage.vehicles.create'),
            $this->garageElement($xpath, '//*[@data-maintenance-form-empty]//a[@data-slot="button"]')->getAttribute('href'),
        );
    }

    public function test_saving_from_the_vehicle_returns_to_it_with_the_new_record_highlighted(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['current_kilometers' => 30_000, 'odometer_at_registration' => 30_000]);

        $response = $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['return_to' => 'vehicle']));

        $maintenance = Maintenance::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('garage.vehicles.show', $vehicle).'#manutencao-'.$maintenance->id)
            ->assertSessionHas('success');
        $this->assertSame('garage', $maintenance->registered_by_type);
        $this->assertNull($maintenance->verified_at);
    }

    public function test_saving_from_the_list_returns_to_the_list(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['current_kilometers' => 30_000, 'odometer_at_registration' => 30_000]);

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle))
            ->assertRedirect(route('garage.maintenances.index'));
    }

    public function test_typed_network_workshop_is_linked_and_free_text_is_kept_as_name(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['current_kilometers' => 30_000, 'odometer_at_registration' => 30_000]);
        $workshop = Workshop::factory()->create(['name' => 'Mecânica Boa Vista', 'city' => 'Campinas']);

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['workshop_name' => 'Mecânica Boa Vista · Campinas']))
            ->assertRedirect();

        $linked = Maintenance::query()->latest('id')->firstOrFail();
        $this->assertSame($workshop->id, (int) $linked->workshop_id);
        $this->assertSame('Mecânica Boa Vista', $linked->workshop_name);
        $this->assertNull($linked->verified_at, 'Oficina informada pelo lojista não aplica o selo.');

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['workshop_name' => 'Auto Center do Zé', 'kilometers' => 31_000]))
            ->assertRedirect();

        $free = Maintenance::query()->latest('id')->firstOrFail();
        $this->assertNull($free->workshop_id);
        $this->assertSame('Auto Center do Zé', $free->workshop_name);
    }

    public function test_category_is_required_and_return_to_only_accepts_vehicle(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['current_kilometers' => 30_000, 'odometer_at_registration' => 30_000]);

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['service_category' => null]))
            ->assertSessionHasErrors('service_category');

        $this->actingAs($this->garage)
            ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['return_to' => 'https://example.com']))
            ->assertSessionHasErrors('return_to');

        $this->assertDatabaseCount('maintenances', 0);
    }

    /**
     * USR-X07: a quilometragem não vem preenchida com o hodômetro de hoje nem ganha min, porque um
     * serviço antigo tem km menor. O formulário leva a faixa de cada veículo (data-mileage, a mesma
     * do Proprietário) e resources/js/garage-maintenance-form.js mostra a faixa da data escolhida.
     */
    public function test_kilometers_field_shows_the_range_for_the_date_without_prefilling_it(): void
    {
        $vehicle = $this->stockVehicle($this->garage, ['current_kilometers' => 62_000, 'odometer_at_registration' => 50_000]);
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->garage->id,
            'maintenance_date' => '2026-03-10',
            'kilometers' => 55_000,
        ]);

        $xpath = $this->garagePage($this->actingAs($this->garage)->get(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])));

        $form = $this->garageElement($xpath, '//form[@data-garage-maintenance-form]');
        $mileage = json_decode($form->getAttribute('data-mileage'), true);
        $this->assertCount(1, $mileage);
        $this->assertSame($vehicle->id, $mileage[0]['id']);
        $this->assertSame(50_000, $mileage[0]['registration_kilometers']);
        $this->assertSame([['date' => '2026-03-10', 'kilometers' => 55_000]], $mileage[0]['records']);

        $km = $this->garageElement($xpath, '//input[@name="kilometers"]');
        $this->assertSame('0', $km->getAttribute('min'));
        $this->assertFalse($km->hasAttribute('value') && $km->getAttribute('value') !== '', 'O campo não vem preenchido com o hodômetro atual.');
        $this->assertTrue($km->hasAttribute('data-maintenance-km'));
        $this->assertContains('kilometers-range', explode(' ', $km->getAttribute('aria-describedby')));
        $this->assertSame('polite', $this->garageElement($xpath, '//*[@id="kilometers-range"][@data-maintenance-km-range]')->getAttribute('aria-live'));
        $this->garageElement($xpath, '//select[@name="vehicle_id"][@data-maintenance-vehicle]');
        $this->garageElement($xpath, '//input[@name="maintenance_date"][@data-maintenance-date]');

        $html = $this->actingAs($this->garage)->get(route('garage.maintenances.create'))->getContent();
        $this->assertStringNotContainsString('garageMaintenanceFormReady', $html, 'O script da tela saiu da view para resources/js/garage-maintenance-form.js.');

        $script = file_get_contents(resource_path('js/garage-maintenance-form.js'));
        $this->assertStringContainsString("import { bindKilometerRange } from './maintenance-kilometer-range';", $script);
        $this->assertStringNotContainsString('kmInput.min', $script);
        $this->assertStringNotContainsString('.value = vehicle', $script);
        $this->assertStringContainsString('initGarageMaintenanceForms();', file_get_contents(resource_path('js/app.js')));
    }

    public function test_validation_errors_are_summarised_and_linked_to_the_fields(): void
    {
        $vehicle = $this->stockVehicle($this->garage);

        $xpath = $this->garagePage(
            $this->actingAs($this->garage)
                ->followingRedirects()
                ->from(route('garage.maintenances.create'))
                ->post(route('garage.maintenances.store'), $this->payload($vehicle, ['maintenance_type' => '', 'service_category' => null]))
        );

        $summary = $this->garageElement($xpath, '//*[@data-slot="form-errors"]');
        $this->assertSame('#service_category', $this->garageElement($xpath, './/a[contains(., "categoria")]', $summary)->getAttribute('href'));
        $this->assertSame('true', $this->garageElement($xpath, '//select[@name="service_category"]')->getAttribute('aria-invalid'));
        $this->assertSame('true', $this->garageElement($xpath, '//input[@name="maintenance_type"]')->getAttribute('aria-invalid'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão pré-venda',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 30_500,
            'service_category' => 'mechanical',
            'is_manufacturer_required' => '0',
        ], $overrides);
    }
}
