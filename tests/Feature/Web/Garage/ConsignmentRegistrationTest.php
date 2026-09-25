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

class ConsignmentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'honda_civic_ms.pdf';

    private const OWNER_DOCUMENT = '37452845854';

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->garage = User::factory()->asGarage()->create(['document' => '11222333000181']);
    }

    public function test_preview_locks_the_switch_when_crlv_belongs_to_someone_else(): void
    {
        $this->importCrlv();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.import.preview'))
            ->assertOk()
            ->assertSee('Veículo em consignação')
            ->assertSee('entra obrigatoriamente como consignação')
            ->assertSee('RODRIGO SANCHES DEVIGO');
    }

    public function test_garage_that_owns_the_crlv_does_not_get_the_locked_switch(): void
    {
        $this->garage->update(['document' => self::OWNER_DOCUMENT]);
        $this->importCrlv();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.import.preview'))
            ->assertOk()
            ->assertDontSee('entra obrigatoriamente como consignação');
    }

    public function test_registration_requires_the_authorization_declaration(): void
    {
        $token = $this->importCrlv();

        $this->actingAs($this->garage)
            ->from(route('garage.vehicles.import.preview'))
            ->post(route('garage.vehicles.store'), $this->payload($token, [
                'consignment_declaration' => null,
            ]))
            ->assertSessionHasErrors('consignment_declaration');

        $this->assertDatabaseCount('vehicle_consignments', 0);
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_registration_requires_a_contact_for_the_owner(): void
    {
        $token = $this->importCrlv();

        $this->actingAs($this->garage)
            ->from(route('garage.vehicles.import.preview'))
            ->post(route('garage.vehicles.store'), $this->payload($token, [
                'consignment_owner_email' => null,
                'consignment_owner_phone' => null,
            ]))
            ->assertSessionHasErrors('consignment_owner_email');

        $this->assertDatabaseCount('vehicle_consignments', 0);
    }

    public function test_garage_registers_a_consigned_vehicle(): void
    {
        $token = $this->importCrlv();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.store'), $this->payload($token))
            ->assertRedirect();

        $vehicle = Vehicle::where('renavam', '01050047521')->firstOrFail();

        $this->assertDatabaseHas('vehicle_consignments', [
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $this->garage->id,
            'owner_name' => 'Rodrigo Sanches Devigo',
            'owner_email' => 'rodrigo@example.com',
            'status' => VehicleConsignment::STATUS_ACTIVE,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
        ]);

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'ownership_type' => 'consignment',
            'is_current_owner' => false,
        ]);

        $consignment = VehicleConsignment::firstOrFail();
        $this->assertNotNull($consignment->declaration_accepted_at);
        $this->assertNotNull($consignment->declaration_ip);
    }

    public function test_power_of_attorney_upload_queues_history_review(): void
    {
        Storage::fake(config('filesystems.default'));
        $token = $this->importCrlv();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.store'), $this->payload($token, [
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect();

        $consignment = VehicleConsignment::firstOrFail();

        $this->assertSame(VehicleConsignment::HISTORY_PENDING, $consignment->history_access_status);
        $this->assertNotNull($consignment->power_of_attorney_path);
    }

    public function test_claim_preview_masks_the_contact_of_a_registered_owner(): void
    {
        $owner = $this->registeredOwner();

        $this->importCrlvForClaim();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.claim.preview'))
            ->assertOk()
            ->assertSee('já tem conta no Revisalog')
            ->assertSee('r******@example.com')
            ->assertDontSee($owner->email)
            ->assertDontSee('manutenção(ões) já registrada(s)');
    }

    public function test_claiming_an_existing_vehicle_creates_the_consignment(): void
    {
        $owner = $this->registeredOwner();
        $token = $this->importCrlvForClaim();

        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.store'), [
                'crlv_verification_token' => $token,
                'is_consignment' => '1',
                'consignment_owner_name' => $owner->name,
                'consignment_declaration' => '1',
            ])
            ->assertRedirect();

        $consignment = VehicleConsignment::firstOrFail();

        $this->assertSame($owner->id, $consignment->owner_user_id);
        $this->assertNull($consignment->owner_email);
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
            base_path('tests/fixtures/crlv/'.self::FIXTURE),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true,
        );
    }

    private function importCrlv(): string
    {
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.import-crlv'), ['crlv' => $this->crlvUpload()]);

        return session('crlv_verification.token');
    }

    private function importCrlvForClaim(): string
    {
        $this->actingAs($this->garage)
            ->post(route('garage.vehicles.claim.import-crlv'), ['crlv' => $this->crlvUpload()]);

        return session('crlv_verification.token');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $token, array $overrides = []): array
    {
        return array_merge([
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
            'color' => 'PRETA',
            'chassis' => '93HFB9640GZ202125',
            'engine' => 'R20Z5-6401964',
            'motorization' => '155CV 2L',
            'current_kilometers' => 50_000,
            'terms_accepted' => '1',
            'crlv_verification_token' => $token,
            'is_consignment' => '1',
            'consignment_owner_name' => 'Rodrigo Sanches Devigo',
            'consignment_owner_email' => 'rodrigo@example.com',
            'consignment_declaration' => '1',
        ], $overrides);
    }
}
