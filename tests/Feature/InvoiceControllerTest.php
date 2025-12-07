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

    /**
     * Test uploading an invoice
     */
    public function test_can_upload_invoice(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
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

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
            'invoice_number' => '12345',
        ]);

        Storage::disk('public')->assertExists('invoices/' . $file->hashName());
    }

    /**
     * Test uploading invoice with invalid file
     */
    public function test_cannot_upload_invoice_with_invalid_file(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('invoice.txt', 100);

        $response = $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test uploading invoice with invalid maintenance
     */
    public function test_cannot_upload_invoice_with_invalid_maintenance(): void
    {
        $file = UploadedFile::fake()->create('invoice.pdf', 100);

        $response = $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => 999,
            'invoice_type' => 'general',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['maintenance_id']);
    }

    /**
     * Test downloading an invoice
     */
    public function test_can_download_invoice(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);
        $filePath = $file->storeAs('invoices', 'test_invoice.pdf', 'public');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $filePath,
            'file_name' => 'invoice.pdf',
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/download");

        // Note: This test may need adjustment based on how file downloads are handled
        // For now, we'll just check that the endpoint exists
        $response->assertStatus(200);
    }

    /**
     * Test deleting an invoice
     */
    public function test_can_delete_invoice(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100);
        $filePath = $file->storeAs('invoices', 'test_invoice.pdf', 'public');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $filePath,
            'file_name' => 'invoice.pdf',
        ]);

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);
    }

    /**
     * Test uploading invoice for specific maintenance item
     */
    public function test_can_upload_invoice_for_maintenance_item(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
        ]);

        $item = \App\Models\MaintenanceItem::factory()->create([
            'maintenance_id' => $maintenance->id,
        ]);

        $file = UploadedFile::fake()->create('item_invoice.pdf', 100);

        $response = $this->postJson('/api/v1/invoices/upload', [
            'file' => $file,
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item->id,
            'invoice_type' => 'item',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $item->id,
            'invoice_type' => 'item',
        ]);
    }
}
