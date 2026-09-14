<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMaintenanceWarrantyTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_can_create_maintenance_with_warranty_templates_via_api(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50_000,
            'odometer_at_registration' => 50_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;

        $orderTemplate = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create([
            'duration_days' => 90,
            'name' => 'Garantia OS',
        ]);
        $itemTemplate = WarrantyTemplate::factory()->forWorkshop($workshop)->itemScope()->create([
            'duration_days' => 180,
            'name' => 'Garantia peça',
        ]);

        $this->actingAsApiUser($workshopUser);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Revisão',
            'maintenance_date' => '2026-02-01',
            'kilometers' => 50_000,
            'service_category' => 'mechanical',
            'general_warranty_template_id' => $orderTemplate->id,
            'items' => [
                [
                    'name' => 'Pastilha de freio',
                    'quantity' => 1,
                    'unit_price' => 120.00,
                    'total_price' => 120.00,
                    'warranty_template_id' => $itemTemplate->id,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.general_warranty.name', 'Garantia OS')
            ->assertJsonPath('data.items.0.warranty.name', 'Garantia peça')
            ->assertJsonPath('data.items.0.has_warranty', true);

        $maintenance = Maintenance::first();
        $this->assertNotNull($maintenance);

        $general = $maintenance->generalWarranty;
        $this->assertNotNull($general);
        $this->assertSame(90, $general->duration_days);
        $this->assertSame('2026-05-02', $general->ends_at->toDateString());

        $item = $maintenance->items->first();
        $this->assertNotNull($item);
        $this->assertFalse((bool) $item->has_warranty);
        $this->assertNull($item->warranty_starts_at);
        $this->assertNull($item->warranty_ends_at);

        $itemWarranty = $item->warranty;
        $this->assertNotNull($itemWarranty);
        $this->assertSame(180, $itemWarranty->duration_days);
        $this->assertSame('Garantia peça', $itemWarranty->name);
    }
}
