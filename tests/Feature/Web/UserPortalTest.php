<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->asUser()->create();
    }

    public function test_user_dashboard_is_accessible(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/dashboard')
            ->assertOk()
            ->assertSee('Meus Veículos');
    }

    public function test_user_can_view_vehicles_page(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/veiculos')
            ->assertOk()
            ->assertSee('Meus Veículos');
    }

    public function test_user_can_create_vehicle(): void
    {
        $this->seed(\Database\Seeders\VehicleCatalogSeeder::class);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', [
                'license_plate' => 'QOS6H54',
                'renavam' => '01159110473',
                'crv_number' => '244043259050',
                'brand' => 'Mercedes-Benz',
                'model' => 'C 180',
                'year' => 2018,
                'crlv_verification_token' => session('crlv_verification.token'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'QOS6H54',
            'crv_number' => '244043259050',
        ]);
    }

    public function test_user_can_view_maintenances_page(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/manutencoes')
            ->assertOk()
            ->assertSee('Manutenções');
    }

    public function test_user_can_create_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->user->tenant_id,
        ]);

        $this->actingAs($this->user)
            ->post('/usuario/manutencoes', [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2025-06-01',
                'service_category' => 'mechanical',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenances', [
            'vehicle_id' => $vehicle->id,
            'user_id' => $this->user->id,
            'maintenance_type' => 'Revisão',
        ]);
    }

    public function test_user_can_create_maintenance_with_invoice_pdf(): void
    {
        Storage::fake('public');

        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->user->tenant_id,
        ]);

        $pdf = UploadedFile::fake()->create('nota-fiscal.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post('/usuario/manutencoes', [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2025-06-01',
                'service_category' => 'mechanical',
                'invoices' => [$pdf],
            ])
            ->assertRedirect();

        $maintenance = Maintenance::where('vehicle_id', $vehicle->id)->first();

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'file_name' => 'nota-fiscal.pdf',
            'invoice_type' => 'general',
        ]);

        Storage::disk('public')->assertExists($maintenance->invoices->first()->file_path);
    }

    public function test_user_sees_warning_when_invoice_pdf_cannot_be_parsed(): void
    {
        Storage::fake('public');

        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->user->tenant_id,
        ]);

        $pdf = UploadedFile::fake()->create('nota-ilegivel.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post('/usuario/manutencoes', [
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'Revisão',
                'maintenance_date' => '2025-06-01',
                'service_category' => 'mechanical',
                'invoices' => [$pdf],
            ])
            ->assertRedirect()
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'nota-ilegivel.pdf')
                && str_contains($message, 'XML'));
    }

    public function test_user_can_view_workshops_directory(): void
    {
        Workshop::factory()->create(['name' => 'Oficina Teste']);

        $this->actingAs($this->user)
            ->get('/usuario/oficinas')
            ->assertOk()
            ->assertSee('Oficina Teste');
    }
}
