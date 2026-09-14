<?php

namespace Tests\Feature;

use App\Enums\WarrantyScope;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceWarranty;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_warranty_template_factory_and_workshop_relation(): void
    {
        $workshop = Workshop::factory()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create([
            'name' => 'Garantia geral',
            'duration_days' => 90,
        ]);

        $this->assertDatabaseHas('warranty_templates', [
            'id' => $template->id,
            'workshop_id' => $workshop->id,
            'tenant_id' => $workshop->tenant_id,
            'scope' => WarrantyScope::Order->value,
        ]);

        $this->assertTrue($workshop->warrantyTemplates->contains($template));
        $this->assertSame($workshop->id, $template->workshop->id);
    }

    public function test_maintenance_warranty_relations(): void
    {
        $workshop = Workshop::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => '2026-01-15',
        ]);
        $item = MaintenanceItem::factory()->create(['maintenance_id' => $maintenance->id]);
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->itemScope()->create();

        $general = MaintenanceWarranty::factory()
            ->fromTemplate(
                WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create(['duration_days' => 60]),
                $maintenance,
            )
            ->orderScope()
            ->create();

        $itemWarranty = MaintenanceWarranty::factory()
            ->fromTemplate($template, $maintenance, $item)
            ->create();

        $maintenance->refresh();

        $this->assertNotNull($maintenance->generalWarranty);
        $this->assertSame($general->id, $maintenance->generalWarranty->id);
        $this->assertCount(2, $maintenance->warranties);
        $this->assertSame($itemWarranty->id, $item->warranty->id);
        $this->assertTrue($workshop->issuedWarranties->contains($general));
    }

    public function test_unique_order_warranty_per_maintenance(): void
    {
        $maintenance = Maintenance::factory()->create();

        MaintenanceWarranty::factory()->forMaintenance($maintenance)->orderScope()->create();

        $this->expectException(QueryException::class);

        MaintenanceWarranty::factory()->forMaintenance($maintenance)->orderScope()->create();
    }

    public function test_unique_item_warranty_per_maintenance_item(): void
    {
        $item = MaintenanceItem::factory()->create();

        MaintenanceWarranty::factory()->itemScope($item)->create();

        $this->expectException(QueryException::class);

        MaintenanceWarranty::factory()->itemScope($item)->create();
    }

    public function test_warranty_dates_computed_from_maintenance_date(): void
    {
        $maintenance = Maintenance::factory()->create([
            'maintenance_date' => '2026-03-01',
        ]);

        $warranty = MaintenanceWarranty::factory()
            ->forMaintenance($maintenance)
            ->create(['duration_days' => 30]);

        $this->assertSame('2026-03-01', $warranty->starts_at->toDateString());
        $this->assertSame('2026-03-31', $warranty->ends_at->toDateString());
    }

    public function test_recompute_dates_when_maintenance_date_changes(): void
    {
        $maintenance = Maintenance::factory()->create([
            'maintenance_date' => '2026-01-01',
        ]);

        $warranty = MaintenanceWarranty::factory()
            ->forMaintenance($maintenance)
            ->create(['duration_days' => 10]);

        $maintenance->update(['maintenance_date' => '2026-02-01']);
        $warranty->recomputeDatesFromMaintenance();
        $warranty->save();

        $warranty->refresh();

        $this->assertSame('2026-02-01', $warranty->starts_at->toDateString());
        $this->assertSame('2026-02-11', $warranty->ends_at->toDateString());
    }

    public function test_template_change_does_not_alter_existing_maintenance_warranty(): void
    {
        $workshop = Workshop::factory()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->create([
            'duration_days' => 30,
            'name' => 'Original',
            'body' => 'Termo original',
        ]);
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => '2026-01-01',
        ]);

        $warranty = MaintenanceWarranty::factory()
            ->fromTemplate($template, $maintenance)
            ->create();
        $warranty->save();

        $template->update([
            'duration_days' => 365,
            'name' => 'Alterado',
            'body' => 'Termo alterado',
        ]);

        $warranty->refresh();

        $this->assertSame(30, $warranty->duration_days);
        $this->assertSame('Original', $warranty->name);
        $this->assertSame('Termo original', $warranty->body);
        $this->assertSame('2026-01-31', $warranty->ends_at->toDateString());
    }

    public function test_workshop_has_active_warranties_detects_vigente_warranty(): void
    {
        $workshop = Workshop::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(5),
        ]);

        MaintenanceWarranty::factory()
            ->forMaintenance($maintenance)
            ->create(['duration_days' => 90]);

        $this->assertTrue($workshop->hasActiveWarranties());

        $expiredMaintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(400),
        ]);

        MaintenanceWarranty::factory()
            ->forMaintenance($expiredMaintenance)
            ->create(['duration_days' => 30]);

        $this->assertTrue($workshop->hasActiveWarranties());
    }

    public function test_workshop_has_no_active_warranties_when_all_expired(): void
    {
        $workshop = Workshop::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'maintenance_date' => now()->subDays(400),
        ]);

        MaintenanceWarranty::factory()
            ->forMaintenance($maintenance)
            ->expired()
            ->create();

        $this->assertFalse($workshop->hasActiveWarranties());
    }

    public function test_warranty_template_is_referenced_when_used(): void
    {
        $template = WarrantyTemplate::factory()->create();
        $maintenance = Maintenance::factory()->create();

        MaintenanceWarranty::factory()->fromTemplate($template, $maintenance)->create();

        $this->assertTrue($template->isReferenced());
    }
}
