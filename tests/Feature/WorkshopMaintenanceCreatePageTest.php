<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopMaintenanceCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders_general_warranty_select_when_workshop_has_order_templates(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($workshopUser->workshop)->orderScope()->create([
            'name' => 'Garantia de 90 dias',
            'duration_days' => 90,
        ]);

        $this->withoutVite()
            ->actingAs($workshopUser)
            ->get(route('workshop.maintenances.create', ['license_plate' => $this->ownedVehiclePlate()]))
            ->assertOk()
            ->assertSee('Garantia geral da OS')
            ->assertSee('name="general_warranty_template_id"', false)
            ->assertSee('value="'.$template->id.'"', false)
            ->assertSee('Garantia de 90 dias (90 dias)');
    }

    public function test_create_page_hides_general_warranty_select_without_order_templates(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        WarrantyTemplate::factory()->forWorkshop($workshopUser->workshop)->itemScope()->create();
        WarrantyTemplate::factory()->forWorkshop($workshopUser->workshop)->orderScope()->inactive()->create();

        $this->withoutVite()
            ->actingAs($workshopUser)
            ->get(route('workshop.maintenances.create', ['license_plate' => $this->ownedVehiclePlate()]))
            ->assertOk()
            ->assertSee('data-warranty-section', false)
            ->assertDontSee('Garantia geral da OS');
    }

    public function test_edit_page_preselects_the_maintenance_general_warranty_template(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create();
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshop->id]);
        MaintenanceWarranty::factory()->fromTemplate($template, $maintenance)->create();

        $this->withoutVite()
            ->actingAs($workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Garantia geral da OS')
            ->assertSee('<option value="'.$template->id.'" selected', false);
    }

    /**
     * O formulário da OS aparece depois de a placa achar um veículo com proprietário.
     */
    private function ownedVehiclePlate(): string
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'CPG1A23']);
        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);

        return 'CPG1A23';
    }
}
