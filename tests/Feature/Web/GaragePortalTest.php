<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GaragePortalTest extends TestCase
{
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_garage_dashboard_is_accessible(): void
    {
        $this->actingAs($this->garage)
            ->get('/garagem/dashboard')
            ->assertOk()
            ->assertSee('Portal da Garagem');
    }

    public function test_garage_can_add_vehicle_to_stock(): void
    {
        $this->seed(VehicleCatalogSeeder::class);
        $this->garage->update(['document' => '374.528.458-54']);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/honda_civic_ms.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->garage)->post('/garagem/estoque/importar-crlv', ['crlv' => $file]);

        $this->actingAs($this->garage)
            ->post('/garagem/estoque', [
                'license_plate' => 'PHF9J95',
                'renavam' => '01050047521',
                'crv_number' => '264600365712',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2016,
                'crlv_verification_token' => session('crlv_verification.token'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'PHF9J95']);
    }

    public function test_garage_can_view_stock(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
        ]);

        $this->actingAs($this->garage)
            ->get('/garagem/estoque')
            ->assertOk()
            ->assertSee($vehicle->brand);
    }
}
