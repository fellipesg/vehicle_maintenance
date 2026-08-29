<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->user = User::factory()->asUser()->create();
    }

    public function test_import_redirects_to_claim_when_vehicle_exists(): void
    {
        Vehicle::factory()->create([
            'license_plate' => 'QOS6H54',
            'renavam' => '01159110473',
            'crv_number' => '244043259050',
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.claim.preview'))
            ->assertSessionHas('claim_vehicle_id');
    }

    public function test_user_can_claim_existing_vehicle_with_crlv(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'QOS6H54',
            'renavam' => '01159110473',
            'crv_number' => '244043259050',
        ]);
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $owner->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $this->actingAs($this->user)
            ->post('/usuario/veiculos/vincular', [
                'crlv_verification_token' => session('crlv_verification.token'),
            ])
            ->assertRedirect();

        $this->assertTrue(
            $this->user->vehicles()->where('vehicle_id', $vehicle->id)->exists()
        );
    }

    public function test_vehicle_show_renders_api_driven_shell(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $this->actingAs($this->user)
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-api-page="vehicle-show"', false)
            ->assertSee('data-vehicle-id="'.$vehicle->id.'"', false)
            ->assertSee('Carregando veículo...');
    }

    public function test_subscribed_user_api_returns_maintenances_for_vehicle(): void
    {
        $this->user->update(['subscription_active' => true]);

        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $maintenance = \App\Models\Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'maintenance_type' => 'Revisão Premium Visível',
        ]);

        $this->actingAsApiUser($this->user);

        $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['maintenance_type' => 'Revisão Premium Visível']);
    }
}
