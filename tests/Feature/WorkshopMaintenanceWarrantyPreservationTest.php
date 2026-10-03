<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopMaintenanceWarrantyPreservationTest extends TestCase
{
    use RefreshDatabase;

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'GAR1A23',
            'current_kilometers' => 40000,
            'odometer_at_registration' => 40000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    public function test_editing_os_keeps_general_warranty_whose_template_was_deactivated(): void
    {
        $template = $this->orderTemplate(['name' => 'Garantia serviço 90 dias', 'duration_days' => 90]);
        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $template->id]);
        $issued = $maintenance->generalWarranty;
        $this->assertNotNull($issued);

        $template->update(['is_active' => false]);

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Garantia geral da OS')
            ->assertSee('<option value="'.$template->id.'" selected', false);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'description' => 'Ajuste na descrição',
                'general_warranty_template_id' => $template->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $kept = $maintenance->fresh()->generalWarranty;
        $this->assertNotNull($kept);
        $this->assertSame($issued->id, $kept->id);
        $this->assertSame('Garantia serviço 90 dias', $kept->name);
        $this->assertSame($issued->ends_at->toDateString(), $kept->ends_at->toDateString());
    }

    public function test_edit_form_summarises_the_issued_general_warranty_and_marks_a_deactivated_template(): void
    {
        $template = $this->orderTemplate(['name' => 'Garantia serviço 90 dias', 'duration_days' => 90]);
        $activeTemplate = $this->orderTemplate(['name' => 'Garantia serviço 30 dias', 'duration_days' => 30]);
        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $template->id]);
        $issued = $maintenance->generalWarranty;

        $template->update(['is_active' => false]);

        $html = $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Garantia emitida: Garantia serviço 90 dias · válida até '.$issued->ends_at->format('d/m/Y'))
            ->assertSee('Garantia serviço 90 dias (90 dias) (modelo desativado, mantido nesta OS)')
            ->assertSee('aria-describedby="general_warranty_template_id-hint"', false)
            ->assertSee('id="general_warranty_template_id-hint"', false)
            ->assertSee('Trocar o modelo ou escolher "Sem garantia geral" encerra a garantia emitida quando você salvar.', false)
            ->getContent();

        $this->assertStringNotContainsString('Garantia serviço 30 dias (30 dias) (modelo desativado', $html);
        $this->assertStringContainsString('<option value="'.$activeTemplate->id.'"', $html);
    }

    public function test_create_form_has_no_issued_general_warranty_summary(): void
    {
        $this->orderTemplate(['name' => 'Garantia serviço 90 dias']);

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.create', ['license_plate' => 'GAR1A23']))
            ->assertOk()
            ->assertSee('Garantia geral da OS')
            ->assertDontSee('Garantia emitida:')
            ->assertDontSee('general_warranty_template_id-hint', false)
            ->assertDontSee('modelo desativado');
    }

    public function test_editing_os_without_the_general_field_keeps_the_issued_warranty(): void
    {
        $template = $this->orderTemplate();
        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $template->id]);
        $issuedId = $maintenance->generalWarranty->id;

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame($issuedId, $maintenance->fresh()->generalWarranty?->id);
    }

    public function test_choosing_no_general_warranty_removes_the_issued_one(): void
    {
        $template = $this->orderTemplate();
        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $template->id]);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'general_warranty_template_id' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($maintenance->fresh()->generalWarranty);
    }

    public function test_switching_general_template_replaces_the_issued_warranty(): void
    {
        $first = $this->orderTemplate(['name' => 'Garantia 30 dias', 'duration_days' => 30]);
        $second = $this->orderTemplate(['name' => 'Garantia 180 dias', 'duration_days' => 180]);
        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $first->id]);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'general_warranty_template_id' => $second->id,
            ]))
            ->assertSessionHasNoErrors();

        $warranty = $maintenance->fresh()->generalWarranty;
        $this->assertSame($second->id, $warranty->warranty_template_id);
        $this->assertSame('Garantia 180 dias', $warranty->name);
        $this->assertSame(1, $maintenance->warranties()->count());
    }

    public function test_deactivated_general_template_cannot_be_linked_to_a_new_os(): void
    {
        $template = $this->orderTemplate();
        $template->update(['is_active' => false]);

        $maintenance = $this->createMaintenance(['general_warranty_template_id' => $template->id]);

        $this->assertNull($maintenance->generalWarranty);
    }

    public function test_editing_os_keeps_item_warranty_whose_template_was_deactivated(): void
    {
        $template = WarrantyTemplate::factory()->forWorkshop($this->workshopUser->workshop)->itemScope()->create([
            'name' => 'Garantia da peça 1 ano',
            'duration_days' => 365,
        ]);
        $maintenance = $this->createMaintenance(['items' => [
            ['name' => 'Amortecedor dianteiro', 'quantity' => 2, 'warranty_template_id' => $template->id],
        ]]);
        $item = $maintenance->items()->firstOrFail();
        $issued = $item->warranty;
        $this->assertNotNull($issued);

        $template->update(['is_active' => false]);

        $this->withoutVite()
            ->actingAs($this->workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Garantia emitida: Garantia da peça 1 ano · válida até '.$issued->ends_at->format('d/m/Y'))
            ->assertSee('Garantia da peça 1 ano (modelo desativado, mantido nesta OS)')
            ->assertSee('<option value="'.$template->id.'" selected', false)
            ->assertSee('data-has-warranty', false);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'description' => 'Ajuste na descrição',
                'items' => [
                    [
                        'id' => $item->id,
                        'name' => 'Amortecedor dianteiro',
                        'quantity' => 2,
                        'warranty_template_id' => $template->id,
                    ],
                    ['name' => 'Batente', 'quantity' => 2, 'warranty_template_id' => ''],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNotNull($item->warranty);
        $this->assertSame($issued->id, $item->warranty->id);
        $this->assertSame('Garantia da peça 1 ano', $item->warranty->name);
        $this->assertSame(2, $maintenance->items()->count());
        $this->assertSame(1, $maintenance->warranties()->count());
    }

    public function test_new_item_rows_receive_the_warranty_chosen_on_their_own_row(): void
    {
        $template = WarrantyTemplate::factory()->forWorkshop($this->workshopUser->workshop)->itemScope()->create();
        $maintenance = $this->createMaintenance(['items' => [
            ['name' => 'Pneu', 'quantity' => 4],
        ]]);
        $tyre = $maintenance->items()->firstOrFail();

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'items' => [
                    ['name' => 'Válvula', 'quantity' => 4, 'warranty_template_id' => $template->id],
                    ['id' => $tyre->id, 'name' => 'Pneu', 'quantity' => 4, 'warranty_template_id' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($tyre->fresh()->warranty);
        $valve = $maintenance->items()->where('name', 'Válvula')->firstOrFail();
        $this->assertSame($template->id, $valve->warranty?->warranty_template_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orderTemplate(array $attributes = []): WarrantyTemplate
    {
        return WarrantyTemplate::factory()->forWorkshop($this->workshopUser->workshop)->orderScope()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createMaintenance(array $extra = []): Maintenance
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload(['license_plate' => 'GAR1A23', ...$extra]))
            ->assertSessionHasNoErrors();

        return Maintenance::latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function osPayload(array $extra = []): array
    {
        return [
            'maintenance_type' => 'Suspensão',
            'maintenance_date' => now()->subDays(10)->toDateString(),
            'kilometers' => 40000,
            'service_category' => 'suspension',
            ...$extra,
        ];
    }
}
