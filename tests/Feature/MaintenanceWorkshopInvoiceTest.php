<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceWorkshopInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_store_with_workshop_id_and_no_invoice_returns_validation_error(): void
    {
        Storage::fake('public');
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);
        $workshop = Workshop::factory()->create();

        $this->actingAs($user)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshop->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2026-01-01',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
            ])
            ->assertSessionHasErrors('invoices');
    }

    public function test_user_store_with_workshop_id_and_invoice_succeeds(): void
    {
        Storage::fake('public');
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);
        $workshop = Workshop::factory()->create();
        $invoice = UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshop->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2026-01-01',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
                'invoices' => [$invoice],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_user_store_without_workshop_id_does_not_require_invoice(): void
    {
        Storage::fake('public');
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10000,
            'odometer_at_registration' => 10000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $this->actingAs($user)
            ->post(route('user.maintenances.store'), [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'workshop_name' => 'Oficina livre',
                'maintenance_date' => '2026-01-01',
                'kilometers' => 10000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_workshop_store_without_invoice_succeeds(): void
    {
        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'HJK1L23',
            'current_kilometers' => 15000,
            'odometer_at_registration' => 15000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $workshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($workshopUser)
            ->post(route('workshop.maintenances.store'), [
                'license_plate' => 'HJK1L23',
                'maintenance_type' => 'Serviço',
                'maintenance_date' => '2026-01-01',
                'kilometers' => 15000,
                'service_category' => 'mechanical',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Maintenance::count());
    }
}
