<?php

namespace Tests\Feature\Web;

use App\Jobs\EmailVehicleMaintenancePdf;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

    public function test_create_page_shows_manual_and_crlv_options(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/veiculos/novo')
            ->assertOk()
            ->assertSee('Importar do CRLV-e')
            ->assertSee('Preencher dados manualmente')
            ->assertSee('Cadastrar veículo');
    }

    public function test_user_can_create_vehicle_manually_without_crlv(): void
    {
        $this->seed(\Database\Seeders\VehicleCatalogSeeder::class);

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', [
                'license_plate' => 'ABC1D23',
                'renavam' => '12345678901',
                'crv_number' => '987654321098',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2020,
                'color' => 'Prata',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
            'crv_number' => '987654321098',
            'brand' => 'Honda',
            'model' => 'Civic',
        ]);

        $this->assertTrue(
            $this->user->vehicles()->where('renavam', '12345678901')->exists()
        );
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

    public function test_user_can_request_vehicle_maintenance_pdf_by_email(): void
    {
        Queue::fake();

        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->user->tenant_id,
        ]);

        $this->actingAs($this->user)
            ->from(route('user.vehicles.show', $vehicle))
            ->post(route('user.vehicles.export-pdf', $vehicle))
            ->assertRedirect(route('user.vehicles.show', $vehicle))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, $this->user->email)
                && str_contains($message, 'processado'));

        Queue::assertPushed(EmailVehicleMaintenancePdf::class, function (EmailVehicleMaintenancePdf $job) use ($vehicle) {
            return $job->user->is($this->user) && $job->vehicle->is($vehicle);
        });
    }

    public function test_maintenance_list_shows_free_text_workshop_name(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->user->tenant_id,
        ]);

        Maintenance::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'vehicle_id' => $vehicle->id,
            'workshop_id' => null,
            'workshop_name' => 'Brothers Londrina',
            'maintenance_type' => 'Troca Válvula',
        ]);

        $this->actingAs($this->user)
            ->get('/usuario/manutencoes')
            ->assertOk()
            ->assertSee('Brothers Londrina');
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
