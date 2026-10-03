<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopMaintenanceItemsFormTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ITM1A23',
            'current_kilometers' => 30000,
            'odometer_at_registration' => 30000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    public function test_create_page_renders_item_list_hooks_and_starts_empty(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.create', ['license_plate' => 'ITM1A23']))
            ->assertOk()
            ->assertSee('data-maintenance-items', false)
            ->assertSee('data-field-prefix="items"', false)
            ->assertSee('data-add-item', false)
            ->assertSee('Adicionar peça ou serviço')
            ->assertSee('data-items-list', false)
            ->assertSee('<template data-item-template>', false)
            ->assertSee('data-items-status', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('Nenhuma peça ou serviço adicionado.');

        $html = $response->getContent();

        // Só a linha modelo do <template>: a lista começa vazia e o estado vazio fica visível.
        $this->assertSame(1, substr_count($html, 'data-item-row'));
        $this->assertDoesNotMatchRegularExpression('/data-items-empty\s+hidden/', $html);
        $this->assertStringContainsString('name="items[__INDEX__][name]"', $html);
        $this->assertStringContainsString('data-item-field="name"', $html);
        $this->assertStringNotContainsString('maintenance-warranty-modal', $html);
        $this->assertStringNotContainsString('id="add-maintenance-item"', $html);
    }

    public function test_create_page_restores_every_submitted_row_after_a_validation_error(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->withSession(['_old_input' => [
                'license_plate' => 'ITM1A23',
                'items' => [
                    ['name' => 'Filtro de ar', 'quantity' => '1'],
                    ['name' => 'Correia dentada', 'quantity' => '2'],
                ],
            ]])
            ->get(route('workshop.maintenances.create'))
            ->assertOk()
            ->assertSee('value="Filtro de ar"', false)
            ->assertSee('value="Correia dentada"', false)
            ->assertSee('name="items[1][name]"', false)
            ->assertSee('aria-label="Remover item 2"', false);

        $this->assertMatchesRegularExpression('/data-items-empty\s+hidden/', $response->getContent());
    }

    public function test_edit_page_renders_existing_items_with_ids_and_labelled_remove_buttons(): void
    {
        $maintenance = $this->createMaintenance([
            ['name' => 'Pastilha de freio', 'quantity' => 1],
            ['name' => 'Disco de freio', 'quantity' => 2],
        ]);
        [$first, $second] = $maintenance->items()->orderBy('id')->get()->all();

        $response = $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('name="items[0][id]" value="'.$first->id.'"', false)
            ->assertSee('name="items[1][id]" value="'.$second->id.'"', false)
            ->assertSee('value="Pastilha de freio"', false)
            ->assertSee('aria-label="Remover item 1"', false)
            ->assertSee('aria-label="Remover item 2"', false)
            ->assertSee('for="items_1_name"', false)
            ->assertSee('id="items_1_name"', false);

        $this->assertMatchesRegularExpression('/data-items-empty\s+hidden/', $response->getContent());
        $this->assertSame(3, substr_count($response->getContent(), 'data-item-row'));
    }

    public function test_workshop_can_register_os_without_items(): void
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $maintenance = Maintenance::first();
        $this->assertNotNull($maintenance);
        $this->assertSame(0, $maintenance->items()->count());
    }

    public function test_update_with_an_empty_list_removes_every_item_and_its_warranty(): void
    {
        $itemTemplate = WarrantyTemplate::factory()->forWorkshop($this->workshopUser->workshop)->itemScope()->create();
        $maintenance = $this->createMaintenance([
            ['name' => 'Bateria', 'quantity' => 1, 'warranty_template_id' => $itemTemplate->id],
            ['name' => 'Lâmpada', 'quantity' => 2],
        ]);
        $this->assertSame(1, $maintenance->warranties()->count());

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload(withPlate: false))
            ->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $maintenance->items()->count());
        $this->assertSame(0, $maintenance->warranties()->count());
    }

    public function test_update_keeps_listed_items_in_place_removes_the_others_and_adds_new_rows(): void
    {
        $maintenance = $this->createMaintenance([
            ['name' => 'Óleo', 'quantity' => 4],
            ['name' => 'Filtro de óleo', 'quantity' => 1],
        ]);
        [$oil, $filter] = $maintenance->items()->orderBy('id')->get()->all();

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload(withPlate: false) + [
                'items' => [
                    ['id' => $filter->id, 'name' => 'Filtro de óleo original', 'quantity' => 1],
                    ['name' => 'Arruela do cárter', 'quantity' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $items = $maintenance->items()->orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertSame($filter->id, $items[0]->id);
        $this->assertSame('Filtro de óleo original', $items[0]->name);
        $this->assertSame('Arruela do cárter', $items[1]->name);
        $this->assertDatabaseMissing('maintenance_items', ['id' => $oil->id]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function createMaintenance(array $items): Maintenance
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload() + ['items' => $items])
            ->assertSessionHasNoErrors();

        return Maintenance::firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function osPayload(bool $withPlate = true): array
    {
        $payload = [
            'maintenance_type' => 'Revisão',
            'maintenance_date' => '2026-03-10',
            'kilometers' => 30000,
            'service_category' => 'mechanical',
        ];

        if ($withPlate) {
            $payload['license_plate'] = 'ITM1A23';
        }

        return $payload;
    }
}
