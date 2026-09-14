<?php

namespace Tests\Feature;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\User;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function workshopUser(): array
    {
        $user = User::factory()->asWorkshop()->create();

        return [$user, $user->workshop];
    }

    public function test_workshop_crud_happy_path_when_no_vigente_warranties(): void
    {
        [$user, $workshop] = $this->workshopUser();

        $this->actingAs($user)
            ->post(route('workshop.warranty-templates.store'), [
                'name' => 'Garantia 90 dias',
                'body' => 'Termo de garantia padrão.',
                'duration_days' => 90,
                'scope' => WarrantyScope::Order->value,
                'is_active' => 1,
            ])
            ->assertRedirect(route('workshop.warranty-templates.index'));

        $template = WarrantyTemplate::first();
        $this->assertNotNull($template);

        $this->actingAs($user)
            ->put(route('workshop.warranty-templates.update', $template), [
                'name' => 'Garantia atualizada',
                'body' => 'Novo termo.',
                'duration_days' => 120,
                'scope' => WarrantyScope::Order->value,
                'is_active' => 1,
            ])
            ->assertRedirect(route('workshop.warranty-templates.index'));

        $template->refresh();
        $this->assertSame('Garantia atualizada', $template->name);
        $this->assertSame(120, $template->duration_days);
    }

    public function test_cannot_update_locked_fields_while_vigente_warranties_exist(): void
    {
        [$user, $workshop] = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create([
            'name' => 'Original',
            'duration_days' => 30,
        ]);

        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(5),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($maintenance)->create(['duration_days' => 90]);

        $this->actingAs($user)
            ->put(route('workshop.warranty-templates.update', $template), [
                'name' => 'Alterado',
                'body' => $template->body,
                'duration_days' => $template->duration_days,
                'scope' => $template->scope->value,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_can_create_new_template_while_vigentes_exist(): void
    {
        [$user, $workshop] = $this->workshopUser();
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(5),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($maintenance)->create(['duration_days' => 90]);

        $this->actingAs($user)
            ->post(route('workshop.warranty-templates.store'), [
                'name' => 'Novo template',
                'body' => 'Termo novo.',
                'duration_days' => 60,
                'scope' => WarrantyScope::Item->value,
                'is_active' => 1,
            ])
            ->assertRedirect(route('workshop.warranty-templates.index'));

        $this->assertDatabaseHas('warranty_templates', [
            'workshop_id' => $workshop->id,
            'name' => 'Novo template',
        ]);
    }

    public function test_can_toggle_is_active_while_vigentes_exist(): void
    {
        [$user, $workshop] = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create(['is_active' => true]);
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(5),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($maintenance)->create(['duration_days' => 90]);

        $this->actingAs($user)
            ->put(route('workshop.warranty-templates.update', $template), [
                'is_active' => 0,
            ])
            ->assertRedirect(route('workshop.warranty-templates.index'));

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_cannot_update_another_workshops_template(): void
    {
        [$user] = $this->workshopUser();
        $otherTemplate = WarrantyTemplate::factory()->create();

        $this->actingAs($user)
            ->put(route('workshop.warranty-templates.update', $otherTemplate), [
                'name' => 'Hack',
                'body' => 'Hack',
                'duration_days' => 10,
                'scope' => WarrantyScope::Order->value,
            ])
            ->assertForbidden();
    }

    public function test_template_change_after_expiry_does_not_rewrite_maintenance_warranty(): void
    {
        [$user, $workshop] = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create(['duration_days' => 30]);
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(400),
        ]);
        $warranty = MaintenanceWarranty::factory()
            ->fromTemplate($template, $maintenance)
            ->create();

        $this->assertFalse($workshop->hasActiveWarranties());

        $this->actingAs($user)
            ->put(route('workshop.warranty-templates.update', $template), [
                'name' => 'Novo nome',
                'body' => 'Novo corpo',
                'duration_days' => 365,
                'scope' => WarrantyScope::Order->value,
            ])
            ->assertRedirect(route('workshop.warranty-templates.index'));

        $warranty->refresh();
        $this->assertSame(30, $warranty->duration_days);
        $this->assertNotSame(365, $warranty->duration_days);
        $this->assertNotSame('Novo nome', $warranty->name);
    }

    public function test_cannot_delete_template_referenced_by_maintenance_warranty(): void
    {
        [$user, $workshop] = $this->workshopUser();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create();
        $maintenance = Maintenance::factory()->create(['workshop_id' => $workshop->id]);
        MaintenanceWarranty::factory()->fromTemplate($template, $maintenance)->create();

        $this->actingAs($user)
            ->delete(route('workshop.warranty-templates.destroy', $template))
            ->assertSessionHasErrors('template');

        $this->assertDatabaseHas('warranty_templates', ['id' => $template->id]);
    }

    public function test_api_cannot_update_locked_fields_while_vigente(): void
    {
        $user = $this->actingAsApiUser(User::factory()->asWorkshop()->create());
        $workshop = $user->workshop;
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create(['name' => 'Original']);
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(3),
        ]);
        MaintenanceWarranty::factory()->forMaintenance($maintenance)->create(['duration_days' => 60]);

        $this->putJson("/api/v1/workshops/{$workshop->id}/warranty-templates/{$template->id}", [
            'name' => 'Alterado',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
