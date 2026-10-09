<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Fluxo web da oficina: OS no chassi, com criação do veículo na hora, sem vazar carro de terceiros.
 */
class WorkshopOwnerlessOsFlowTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function osPayload(array $extra = []): array
    {
        return $extra + [
            'maintenance_type' => 'Revisão 50.000 km',
            'maintenance_date' => now()->toDateString(),
            'kilometers' => 50_000,
            'service_category' => 'mechanical',
        ];
    }

    public function test_workshop_creates_the_vehicle_by_chassis_and_registers_an_ownerless_os(): void
    {
        $workshop = $this->workshopAccount();

        $this->actingAs($workshop)->post(route('workshop.maintenances.store'), $this->osPayload([
            'chassis' => self::OWNERLESS_CHASSIS,
            'new_vehicle' => ['brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021],
        ]))->assertRedirect();

        $vehicle = Vehicle::findByChassis(self::OWNERLESS_CHASSIS);
        $this->assertNotNull($vehicle);
        $this->assertNull($vehicle->license_plate);
        $this->assertNull($vehicle->renavam);
        $this->assertFalse($vehicle->hasCurrentOwner());

        $maintenance = Maintenance::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
        $this->assertNull($maintenance->tenant_id);
        $this->assertSame(Maintenance::OWNER_PENDING, $maintenance->owner_status);
        $this->assertSame(Maintenance::ATTACHMENTS_NONE, $maintenance->attachments_status);
        $this->assertSame($workshop->workshop->id, $maintenance->workshop_id);
        $this->assertNotNull($maintenance->verification_code);
    }

    public function test_attachments_on_an_ownerless_os_become_pending(): void
    {
        Storage::fake('local');
        $workshop = $this->workshopAccount();

        $this->actingAs($workshop)->post(route('workshop.maintenances.store'), $this->osPayload([
            'chassis' => self::OWNERLESS_CHASSIS,
            'new_vehicle' => ['brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021],
            'photos' => ['vehicle_after' => [UploadedFile::fake()->image('carro.jpg')]],
        ]))->assertRedirect();

        $maintenance = Maintenance::query()->firstOrFail();
        $this->assertSame(1, $maintenance->photos()->count());
        $this->assertSame(Maintenance::ATTACHMENTS_PENDING, $maintenance->attachments_status);
    }

    public function test_existing_ownerless_vehicle_is_reused(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();

        $this->actingAs($workshop)->post(route('workshop.maintenances.store'), $this->osPayload([
            'chassis' => self::OWNERLESS_CHASSIS,
        ]))->assertRedirect();

        $this->assertSame(1, Vehicle::count());
        $this->assertSame($vehicle->id, Maintenance::query()->firstOrFail()->vehicle_id);
    }

    public function test_owned_chassis_is_refused_without_leaking_the_car(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle(['brand' => 'Ferrari', 'model' => 'Secreta']);
        $this->ownerOf($vehicle);

        $this->actingAs($workshop)->post(route('workshop.maintenances.store'), $this->osPayload([
            'chassis' => self::OWNERLESS_CHASSIS,
            'new_vehicle' => ['brand' => 'X', 'model' => 'Y', 'year' => 2020],
        ]))->assertSessionHasErrors('chassis');

        $this->assertSame(0, Maintenance::count());
        $this->assertSame(1, Vehicle::count());

        $this->actingAs($workshop)
            ->get(route('workshop.maintenances.create', ['chassis' => self::OWNERLESS_CHASSIS]))
            ->assertOk()
            ->assertSee('Este chassi já tem proprietário')
            ->assertDontSee('Ferrari')
            ->assertDontSee('Secreta')
            ->assertDontSee('data-maintenance-os-form', false);
    }

    public function test_create_page_offers_vehicle_fields_for_an_unknown_chassis(): void
    {
        $workshop = $this->workshopAccount();

        $this->actingAs($workshop)
            ->get(route('workshop.maintenances.create', ['chassis' => self::OWNERLESS_CHASSIS]))
            ->assertOk()
            ->assertSee('data-new-vehicle-fields', false)
            ->assertSee('Não inclua nome, CPF, telefone ou placa do cliente.');
    }

    public function test_plate_flow_still_registers_on_an_owned_vehicle_with_the_owner_tenant(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23', 'current_kilometers' => 10_000, 'odometer_at_registration' => 10_000]);
        $owner = $this->ownerOf($vehicle);

        $this->actingAs($workshop)->post(route('workshop.maintenances.store'), $this->osPayload([
            'license_plate' => 'ABC1D23',
        ]))->assertRedirect();

        $maintenance = Maintenance::query()->firstOrFail();
        $this->assertSame($owner->tenant_id, $maintenance->tenant_id);
        $this->assertNull($maintenance->owner_status);
    }

    public function test_workshop_detail_shows_the_invite_card_only_while_ownerless(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshop, $vehicle);

        $this->actingAs($workshop)->get(route('workshop.maintenances.show', $maintenance))
            ->assertOk()
            ->assertSee('Avisar o cliente')
            ->assertSee('Registro sem proprietário no RevisaLog');

        $this->ownerOf($vehicle);

        $this->actingAs($workshop)->get(route('workshop.maintenances.show', $maintenance))
            ->assertOk()
            ->assertDontSee('Avisar o cliente');
    }

    public function test_other_tenant_cannot_see_the_ownerless_os(): void
    {
        $workshop = $this->workshopAccount();
        $maintenance = $this->withPendingAttachments($this->ownerlessRecord($workshop, $this->ownerlessVehicle()));
        $stranger = User::factory()->asUser()->create()->refresh();

        $this->actingAs($stranger)->get(route('user.maintenances.show', $maintenance))->assertForbidden();
        $this->actingAs($this->workshopAccount())->get(route('workshop.maintenances.show', $maintenance))->assertForbidden();
    }
}
