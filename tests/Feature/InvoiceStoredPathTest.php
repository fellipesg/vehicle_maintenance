<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceStoredPathTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL_NAME = 'Joao Silva CPF 123.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_api_upload_stores_invoice_under_random_path(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => UploadedFile::fake()->create(self::ORIGINAL_NAME, 100, 'application/pdf'),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertCreated();

        $this->assertStoredPathIsRandom(Invoice::query()->firstOrFail());
    }

    public function test_web_upload_stores_invoice_under_random_path(): void
    {
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
                'invoices' => [UploadedFile::fake()->create(self::ORIGINAL_NAME, 100, 'application/pdf')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertStoredPathIsRandom(Invoice::query()->firstOrFail());
    }

    private function assertStoredPathIsRandom(Invoice $invoice): void
    {
        $this->assertStringNotContainsString('Joao', $invoice->file_path);
        $this->assertStringNotContainsString('CPF', $invoice->file_path);
        $this->assertMatchesRegularExpression('#^invoices/[A-Za-z0-9]{40}\.pdf$#', $invoice->file_path);
        $this->assertSame(self::ORIGINAL_NAME, $invoice->file_name);
        Storage::disk('public')->assertExists($invoice->file_path);
    }
}
