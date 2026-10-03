<?php

namespace Tests\Feature\Web\Garage;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entrada de um veículo em consignação pelo assistente do Lojista, nas duas situações: veículo novo
 * na RevisaLog e veículo que já está cadastrado em outra conta.
 */
class ConsignmentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_DOCUMENT = '37452845854';

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->garage = User::factory()->asGarage()->create(['document' => '11222333000181']);
    }

    public function test_crlv_of_a_third_party_sends_the_garage_to_the_consignment_step(): void
    {
        $this->importCrlv();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.store'), $this->vehiclePayload())
            ->assertRedirect(route('garage.vehicles.consignment'));

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.consignment'))
            ->assertOk()
            ->assertSee('Veículo em consignação')
            ->assertSee('RODRIGO SANCHES DEVIGO');

        $this->assertSame(0, Vehicle::count(), 'Nada é gravado antes da declaração.');
    }

    public function test_garage_that_owns_the_crlv_is_registered_as_owner(): void
    {
        $this->garage->update(['document' => self::OWNER_DOCUMENT]);
        $this->importCrlv();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.store'), $this->vehiclePayload())
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('vehicle_consignments', 0);
        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $this->garage->id,
            'ownership_type' => 'owner',
            'is_current_owner' => true,
        ]);
    }

    public function test_declaration_creates_the_consignment_for_a_new_vehicle(): void
    {
        $this->importCrlv();
        $this->actingAs($this->garage)->post(route('garage.vehicles.store'), $this->vehiclePayload());

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.store'), $this->declaration())
            ->assertRedirect(route('garage.vehicles.index'));

        $vehicle = Vehicle::where('renavam', '01050047521')->firstOrFail();

        $this->assertDatabaseHas('vehicle_consignments', [
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $this->garage->id,
            'owner_name' => 'Rodrigo Sanches Devigo',
            'owner_email' => 'rodrigo@example.com',
            'status' => VehicleConsignment::STATUS_ACTIVE,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
        ]);

        $consignment = VehicleConsignment::firstOrFail();
        $this->assertNotNull($consignment->declaration_accepted_at);
        $this->assertNotNull($consignment->declaration_ip);
        $this->assertSame(self::OWNER_DOCUMENT, $consignment->owner_document);
    }

    public function test_power_of_attorney_upload_queues_the_history_review(): void
    {
        Storage::fake(config('filesystems.default'));

        $this->importCrlv();
        $this->actingAs($this->garage)->post(route('garage.vehicles.store'), $this->vehiclePayload());

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.store'), $this->declaration([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect();

        $consignment = VehicleConsignment::firstOrFail();

        $this->assertSame(VehicleConsignment::HISTORY_PENDING, $consignment->history_access_status);
        $this->assertNotNull($consignment->power_of_attorney_path);
        $this->assertNotNull($consignment->history_requested_at);
    }

    public function test_consignment_step_masks_the_contact_of_a_registered_owner(): void
    {
        $owner = $this->registeredOwner();

        $this->importCrlvForClaim();
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')])
            ->assertRedirect(route('garage.vehicles.consignment'));

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.consignment'))
            ->assertOk()
            ->assertSee('já tem conta na RevisaLog')
            ->assertSee('r******@example.com')
            ->assertDontSee($owner->email);
    }

    public function test_claiming_an_existing_vehicle_links_the_owner_account(): void
    {
        $owner = $this->registeredOwner();

        $this->importCrlvForClaim();
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')]);

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.consignment.store'), [
                'consignment_owner_name' => $owner->name,
                'consignment_declaration' => '1',
            ])
            ->assertRedirect(route('garage.vehicles.index'));

        $consignment = VehicleConsignment::firstOrFail();

        $this->assertSame($owner->id, $consignment->owner_user_id);
        $this->assertNull($consignment->owner_email, 'O contato de quem já tem conta não passa pelo formulário.');
        $this->assertTrue($consignment->isActive());
    }

    private function registeredOwner(): User
    {
        $owner = User::factory()->asUser()->create([
            'email' => 'rodrigo@example.com',
            'phone' => '67999881234',
        ]);

        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'chassis' => '93HFB9640GZ202125',
        ]);

        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $owner->tenant_id,
        ]);

        return $owner;
    }

    private function crlvUpload(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true,
        );
    }

    private function importCrlv(): void
    {
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);
    }

    private function importCrlvForClaim(): void
    {
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.import-crlv'), ['crlv' => $this->crlvUpload()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vehiclePayload(): array
    {
        return [
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
            'current_kilometers' => 85_000,
            'terms_accepted' => '1',
            'crlv_verification_token' => session('crlv_verification.token'),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function declaration(array $overrides = []): array
    {
        return array_merge([
            'consignment_owner_name' => 'Rodrigo Sanches Devigo',
            'consignment_owner_email' => 'rodrigo@example.com',
            'consignment_declaration' => '1',
        ], $overrides);
    }
}
