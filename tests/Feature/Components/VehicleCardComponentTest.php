<?php

namespace Tests\Feature\Components;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-vehicle.card>: card inteiro clicável, capa sem corte, placa, km e resumo de procedência.
 */
class VehicleCardComponentTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_whole_card_is_one_link_with_uncropped_cover_plate_km_and_provenance(): void
    {
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Fiat',
            'model' => 'Uno',
            'year' => 2018,
            'color' => 'Branco',
            'license_plate' => 'UNO1A23',
            'current_kilometers' => 81500,
            'cover_photo_path' => 'vehicle-covers/uno.jpg',
        ]);
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->count(2)->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);

        $xpath = $this->renderUi('<x-vehicle.card :vehicle="$vehicle" href="/usuario/veiculos/9" />', ['vehicle' => $vehicle->fresh()]);

        $card = $this->uiElement($xpath, '//article[@data-slot="vehicle-card"]');
        $this->assertHasClasses(['relative', 'rounded-card', 'motion-reduce:transition-none'], $card);
        $this->assertSame('veiculo-'.$vehicle->id.'-titulo', $card->getAttribute('aria-labelledby'));

        $links = $xpath->query('//a');
        $this->assertSame(1, $links->length, 'Um link só, o título, esticado sobre o card.');
        $this->assertSame('/usuario/veiculos/9', $links->item(0)->getAttribute('href'));
        $this->assertSame('Fiat Uno', $this->uiText($this->uiElement($xpath, '//h3/a')));
        $this->assertStringContainsString('after:absolute', $links->item(0)->getAttribute('class'));

        $this->assertSame('card', $this->uiElement($xpath, '//*[@data-vehicle-cover]')->getAttribute('data-vehicle-cover'));
        $this->assertStringContainsString('object-contain', $this->uiElement($xpath, '//img[@alt="Capa do Fiat Uno"]')->getAttribute('class'));

        $text = $this->uiText($card);
        $this->assertStringContainsString('2018 · Branco', $text);
        $this->assertStringContainsString('UNO1A23', $text);
        $this->assertStringContainsString('81.500 km', $text);
        $this->assertStringContainsString('1 com selo · 2 declaradas', $text);
        $this->assertSame(3, $this->uiCount($xpath, '//ol[contains(@class, "prov-dots-row")]/li'));
    }

    public function test_vehicle_without_maintenance_and_slots(): void
    {
        $vehicle = Vehicle::factory()->create(['cover_photo_path' => null, 'cover_photo_portrait_path' => null]);

        $xpath = $this->renderUi(<<<'BLADE'
            <x-vehicle.card :vehicle="$vehicle" href="/garagem/estoque/1" as="li" heading-level="h2" add-cover-url="/editar">
                <x-slot:badges><span data-badge>Consignação</span></x-slot:badges>
                <x-slot:actions><a href="/editar" data-edit>Editar</a></x-slot:actions>
            </x-vehicle.card>
            BLADE, ['vehicle' => $vehicle->loadCount('maintenances')]);

        $this->assertSame(1, $this->uiCount($xpath, '//li[@data-slot="vehicle-card"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//h2/a'));
        $this->assertStringContainsString('Sem manutenções registradas', $this->uiText($this->uiElement($xpath, '//li')));
        $this->assertSame(0, $this->uiCount($xpath, '//ol'));
        $this->assertSame(1, $this->uiCount($xpath, '//span[@data-badge]'));
        $this->assertHasClasses(['relative', 'z-10'], $this->uiElement($xpath, '//a[@data-edit]/..'));
        $this->assertSame('Adicionar capa', $this->uiText($this->uiElement($xpath, '//*[@data-vehicle-cover]//a[@href="/editar"]')));
    }

    public function test_href_is_required(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->assertUiRejects('<x-vehicle.card :vehicle="$vehicle" />', 'x-vehicle.card precisa de href', ['vehicle' => $vehicle]);
    }

    public function test_cards_in_a_list_do_not_lazy_load(): void
    {
        Vehicle::factory()->count(2)->create()->each(fn (Vehicle $vehicle) => Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]));
        $vehicles = Vehicle::query()
            ->with('provenanceStripMaintenances')
            ->withCount(['maintenances', 'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at')])
            ->get();

        $queries = $this->countQueries(function () use ($vehicles): void {
            $this->blade('@foreach($vehicles as $vehicle)<x-vehicle.card :vehicle="$vehicle" href="/v" />@endforeach', ['vehicles' => $vehicles]);
        });

        $this->assertSame(0, $queries, 'Com o eager load indicado, o card não consulta o banco.');
    }
}
