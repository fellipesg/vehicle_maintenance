<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
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
            ->get(route('workshop.maintenances.create'))
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
            ->get(route('workshop.maintenances.create'))
            ->assertOk()
            ->assertDontSee('Garantia geral da OS');
    }

    public function test_edit_page_preselects_the_maintenance_general_warranty_template(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create();
        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshop->id]);
        MaintenanceWarranty::factory()->fromTemplate($template, $maintenance)->create();

        $this->withoutVite()
            ->actingAs($workshopUser)
            ->get(route('workshop.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Garantia geral da OS')
            ->assertSee('<option value="'.$template->id.'" selected', false);
    }
}
