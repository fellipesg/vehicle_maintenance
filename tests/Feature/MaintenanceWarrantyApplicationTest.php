<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceWarrantyApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_os_with_general_and_item_warranties_snapshots_template_and_computes_ends_at(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'current_kilometers' => 50000,
            'odometer_at_registration' => 50000,
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

        $this->actingAs($workshopUser)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'ABC1D23',
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2026-02-01',
                'kilometers' => 50000,
                'service_category' => 'mechanical',
                'general_warranty_template_id' => $orderTemplate->id,
                'items' => [
                    [
                        'name' => 'Pastilha de freio',
                        'quantity' => 1,
                        'warranty_template_id' => $itemTemplate->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $maintenance = Maintenance::first();
        $this->assertNotNull($maintenance);

        $general = $maintenance->generalWarranty;
        $this->assertNotNull($general);
        $this->assertSame(90, $general->duration_days);
        $this->assertSame('Garantia OS', $general->name);
        $this->assertSame('2026-02-01', $general->starts_at->toDateString());
        $this->assertSame('2026-05-02', $general->ends_at->toDateString());

        $itemWarranty = $maintenance->items->first()->warranty;
        $this->assertNotNull($itemWarranty);
        $this->assertSame(180, $itemWarranty->duration_days);
        $this->assertSame('Garantia peça', $itemWarranty->name);
    }

    public function test_editing_maintenance_date_recomputes_warranty_dates_on_that_os(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'XYZ9Z99',
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create([
            'duration_days' => 30,
        ]);

        $this->actingAs($workshopUser)->post(route('workshop.maintenances.store'), [
            'license_plate' => 'XYZ9Z99',
            'maintenance_type' => 'Serviço',
            'maintenance_date' => '2026-01-01',
            'kilometers' => 10000,
            'service_category' => 'mechanical',
            'general_warranty_template_id' => $template->id,
        ]);

        $maintenance = Maintenance::first();
        $warranty = $maintenance->generalWarranty;
        $this->assertSame('2026-01-31', $warranty->ends_at->toDateString());

        $this->actingAs($workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), [
                'maintenance_type' => 'Serviço',
                'maintenance_date' => '2026-03-01',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
                'general_warranty_template_id' => $template->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $maintenance->refresh();
        $updatedWarranty = $maintenance->generalWarranty;
        $this->assertNotNull($updatedWarranty);
        $this->assertSame('2026-03-01', $updatedWarranty->starts_at->toDateString());
        $this->assertSame('2026-03-31', $updatedWarranty->ends_at->toDateString());
    }

    public function test_later_template_edit_does_not_change_issued_warranty_rows(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'DEF4G56',
            'current_kilometers' => 20000,
            'odometer_at_registration' => 20000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create([
            'duration_days' => 60,
            'name' => 'Termo original',
        ]);

        $this->actingAs($workshopUser)->post(route('workshop.maintenances.store'), [
            'license_plate' => 'DEF4G56',
            'maintenance_type' => 'Serviço',
            'maintenance_date' => now()->subDays(400)->toDateString(),
            'kilometers' => 20000,
            'service_category' => 'mechanical',
            'general_warranty_template_id' => $template->id,
        ]);

        $warranty = Maintenance::first()->generalWarranty;
        $originalEndsAt = $warranty->ends_at->toDateString();

        $template->update(['duration_days' => 365, 'name' => 'Termo novo']);

        $warranty->refresh();
        $this->assertSame(60, $warranty->duration_days);
        $this->assertSame('Termo original', $warranty->name);
        $this->assertSame($originalEndsAt, $warranty->ends_at->toDateString());
    }
}
