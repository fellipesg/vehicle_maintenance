<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Maintenance;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_can_upload_invoice(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);

        $response = $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
            'invoice_number' => '12345',
            'invoice_date' => '2024-01-15',
            'total_amount' => 500.00,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('parsed_items_count', 0);

        $this->assertStringContainsString('invoice.pdf', $response->json('parse_warning'));
        $this->assertStringContainsString('XML', $response->json('parse_warning'));

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ]);
    }

    public function test_imports_items_from_nfe_pdf(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/divesa_nfe_80000.pdf'),
            'divesa_nfe.pdf',
            'application/pdf',
            null,
            true
        );

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])
            ->assertCreated()
            ->assertJsonPath('parsed_items_count', 10);

        $this->assertDatabaseCount('maintenance_items', 10);
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '31715',
        ]);
        $this->assertDatabaseHas('maintenance_items', [
            'maintenance_id' => $maintenance->id,
            'name' => 'FILTRO DE POEIRA',
        ]);
    }

    public function test_imports_items_from_nfe_xml(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/troia_nfe_4250.xml'),
            'troia_nfe.xml',
            'application/xml',
            null,
            true
        );

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])
            ->assertCreated()
            ->assertJsonPath('parsed_items_count', 5)
            ->assertJsonPath('message', 'Nota fiscal salva e 5 itens importados da NF-e.');

        $this->assertDatabaseCount('maintenance_items', 5);
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '4250',
        ]);
        $this->assertDatabaseHas('maintenance_items', [
            'maintenance_id' => $maintenance->id,
            'name' => 'Óleo Lubrificante 0w20 Idemitsu',
            'part_number' => '2059',
        ]);
    }

    public function test_imports_items_from_divesa_nfe_xml_33893(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/divesa_nfe_33893.xml'),
            'Nota_Fiscal_33893-3.xml',
            'text/plain',
            null,
            true
        );

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])
            ->assertCreated()
            ->assertJsonPath('parsed_items_count', 6)
            ->assertJsonMissingPath('parse_warning');

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '33893',
        ]);
    }

    public function test_accepts_xml_with_text_plain_mime(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/troia_nfe_4250.xml'),
            'Nota_Fiscal_33893-3.xml',
            'text/plain',
            null,
            true
        );

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertCreated();
    }

    public function test_cannot_upload_invoice_for_other_tenant_maintenance(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertForbidden();
    }

    public function test_cannot_upload_invoice_with_invalid_file(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = UploadedFile::fake()->create('invoice.txt', 100);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_cannot_upload_invoice_with_invalid_maintenance(): void
    {
        $this->actingAsApiUser();
        $file = UploadedFile::fake()->create('invoice.pdf', 100);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => 999,
            'invoice_type' => 'general',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['maintenance_id']);
    }

    public function test_can_download_invoice(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);
        $filePath = $file->storeAs('invoices', 'test_invoice.pdf', 'public');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $filePath,
            'file_name' => 'invoice.pdf',
        ]);

        $this->getJson("/api/v1/invoices/{$invoice->id}/download")
            ->assertOk();
    }

    public function test_can_delete_invoice(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);
        $filePath = $file->storeAs('invoices', 'test_invoice.pdf', 'public');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $filePath,
            'file_name' => 'invoice.pdf',
        ]);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_can_upload_invoice_for_maintenance_item(): void
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $item = \App\Models\MaintenanceItem::factory()->create([
            'maintenance_id' => $maintenance->id,
        ]);

        $file = UploadedFile::fake()->create('item_invoice.pdf', 100);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item->id,
            'invoice_type' => 'item',
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item->id,
            'invoice_type' => 'item',
        ]);
    }
}
