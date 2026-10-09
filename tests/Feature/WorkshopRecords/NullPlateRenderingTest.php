<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Veículo sem placa nem RENAVAM (criado pela oficina) renderiza nas telas principais.
 */
class NullPlateRenderingTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    public function test_owner_pages_render_without_plate(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAs($owner);
        $this->get(route('user.dashboard'))->assertOk();
        $this->get(route('user.vehicles.index'))->assertOk()->assertSee('Argo');
        $this->get(route('user.vehicles.show', $vehicle))->assertOk()->assertSee('Placa não informada');
        $this->get(route('user.vehicles.edit', $vehicle))->assertOk();
        $this->get(route('user.maintenances.index'))->assertOk();
        $this->get(route('user.maintenances.show', $record))->assertOk();
        $this->get(route('user.workshop-records.index'))->assertOk();
        $this->get(route('vehicle.search', ['identifier' => self::OWNERLESS_CHASSIS]))->assertOk();
    }

    public function test_workshop_pages_render_without_plate(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshop, $vehicle);

        $this->actingAs($workshop);
        $this->get(route('workshop.maintenances.index'))->assertOk()->assertSee('Argo');
        $this->get(route('workshop.maintenances.show', $record))->assertOk()->assertSee('Argo');
        $this->get(route('workshop.maintenances.edit', $record))->assertOk();
        $this->get(route('workshop.maintenances.create', ['chassis' => self::OWNERLESS_CHASSIS]))->assertOk()->assertSee('Placa não informada');
        $this->get(route('workshop.dashboard'))->assertOk();
        $this->get(route('workshop.reviews.index'))->assertOk();
        $this->get(route('vehicle.search', ['identifier' => self::OWNERLESS_CHASSIS]))->assertOk();
    }

    public function test_admin_pages_render_without_plate(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin);
        $this->get(route('admin.vehicles.index'))->assertOk()->assertSee('Argo');
        $this->get(route('admin.vehicles.show', $vehicle))->assertOk();
        $this->get(route('admin.maintenances.index'))->assertOk();
    }

    public function test_pdf_export_data_handles_a_vehicle_without_plate(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);

        $result = app(\App\Services\Vehicle\VehicleMaintenancePdfExporter::class)->generate($vehicle->fresh(), maskIdentifiers: true);

        $this->assertStringContainsString('sem-placa', app(\App\Services\Vehicle\VehicleMaintenancePdfExporter::class)->downloadFilename($vehicle));
        $this->assertNotSame('', $result['content']);
    }
}
