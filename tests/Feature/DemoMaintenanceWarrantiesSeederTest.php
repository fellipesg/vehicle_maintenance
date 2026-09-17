<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceWarranty;
use App\Models\Vehicle;
use App\Models\Workshop;
use Database\Seeders\DemoMaintenanceWarrantiesSeeder;
use Database\Seeders\DemoWorkshopAccountsSeeder;
use Database\Seeders\DemoWorkshopLogosSeeder;
use Database\Seeders\DevPortalUsersSeeder;
use Database\Seeders\FelipeVehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMaintenanceWarrantiesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCoversDisk('r2');
    }

    public function test_seeder_is_idempotent_with_felipe_vehicle_chain(): void
    {
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        (new DevPortalUsersSeeder)->run();
        (new DemoWorkshopAccountsSeeder)->run();
        (new FelipeVehicleSeeder)->run();
        (new DemoWorkshopLogosSeeder)->run();

        $seeder = new DemoMaintenanceWarrantiesSeeder;
        $seeder->run();
        $seeder->run();

        $vehicle = Vehicle::query()->where('license_plate', DemoMaintenanceWarrantiesSeeder::PLATE)->firstOrFail();
        $divesa = Workshop::query()->where('name', DemoWorkshopAccountsSeeder::DIVESA_NAME)->firstOrFail();

        $maintenance = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('workshop_id', $divesa->id)
            ->where('maintenance_type', 'Revisão B (Assyst B)')
            ->whereDate('maintenance_date', '2026-03-10')
            ->firstOrFail();

        $this->assertNotNull($maintenance->generalWarranty);
        $this->assertSame(
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA,
            $maintenance->generalWarranty->name,
        );
        $this->assertStringContainsString(
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ORDER_DIVESA,
            $maintenance->generalWarranty->body,
        );

        $batteryItem = $maintenance->items()->where('name', 'like', '%Bateria%')->firstOrFail();
        $this->assertNotNull($batteryItem->warranty);
        $this->assertSame(
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA,
            $batteryItem->warranty->name,
        );

        $this->assertSame(1, MaintenanceWarranty::query()
            ->where('maintenance_id', $maintenance->id)
            ->where('scope', 'order')
            ->count());

        $this->assertGreaterThanOrEqual(96_000, (int) $vehicle->fresh()->current_kilometers);

        $this->assertSame(1, Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Demo controle — sem logo/garantia')
            ->count());

        $controlMaintenance = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Demo controle — sem logo/garantia')
            ->firstOrFail();

        $this->assertSame('2025-11-22', $controlMaintenance->maintenance_date->toDateString());
    }

    public function test_seeder_skips_when_felipe_vehicle_missing(): void
    {
        $seeder = new DemoMaintenanceWarrantiesSeeder;
        $seeder->run();

        $this->assertSame(0, MaintenanceWarranty::count());
    }

    public function test_seeder_recreates_item_warranty_after_felipe_reseeds_items(): void
    {
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        (new DevPortalUsersSeeder)->run();
        (new DemoWorkshopAccountsSeeder)->run();
        (new FelipeVehicleSeeder)->run();
        (new DemoMaintenanceWarrantiesSeeder)->run();

        (new FelipeVehicleSeeder)->run();
        (new DemoMaintenanceWarrantiesSeeder)->run();

        $vehicle = Vehicle::query()->where('license_plate', DemoMaintenanceWarrantiesSeeder::PLATE)->firstOrFail();
        $maintenance = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Revisão B (Assyst B)')
            ->firstOrFail();

        $batteryItem = $maintenance->items()->where('name', 'like', '%Bateria%')->firstOrFail();
        $this->assertSame(
            DemoMaintenanceWarrantiesSeeder::ANCHOR_ITEM_DIVESA,
            $batteryItem->fresh()->warranty?->name,
        );
    }

    public function test_seeder_renames_legacy_brothers_titles_in_place_instead_of_duplicating(): void
    {
        Workshop::factory()->create(['name' => DemoWorkshopAccountsSeeder::BROTHERS_NAME]);

        (new DevPortalUsersSeeder)->run();
        (new DemoWorkshopAccountsSeeder)->run();
        (new FelipeVehicleSeeder)->run();
        (new DemoMaintenanceWarrantiesSeeder)->run();

        $vehicle = Vehicle::query()->where('license_plate', DemoMaintenanceWarrantiesSeeder::PLATE)->firstOrFail();
        $legacy = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'Demo Brothers — pastilhas (garantia vigente)')
            ->firstOrFail();
        $legacy->forceFill(['maintenance_type' => 'Demo Brothers — pastilhas (vigente)'])->save();

        (new DemoMaintenanceWarrantiesSeeder)->run();

        $renamed = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('maintenance_type', 'like', 'Demo Brothers — pastilhas%')
            ->get();

        $this->assertCount(1, $renamed);
        $this->assertSame($legacy->id, $renamed->first()->id);
        $this->assertSame('Demo Brothers — pastilhas (garantia vigente)', $renamed->first()->maintenance_type);
        $this->assertNotNull($renamed->first()->generalWarranty);
    }
}
