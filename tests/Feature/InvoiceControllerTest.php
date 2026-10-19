<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\User;
use App\Models\Vehicle;
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
            ->assertJsonPath('message', 'Invoice saved and 5 items imported from NF-e.');

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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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
        $this->attachVehicleToUser($user, $vehicle);
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

    public function test_cannot_delete_invoice_from_other_tenant(): void
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
        $filePath = $file->storeAs('invoices', 'other_tenant_invoice.pdf', 'public');

        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $filePath,
            'file_name' => 'invoice.pdf',
        ]);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_cannot_upload_a_file_renamed_to_pdf(): void
    {
        $maintenance = $this->ownedMaintenance();

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $this->uploadWithContent("#!/bin/sh\necho pwned\n", 'nota.pdf'),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    public function test_cannot_upload_a_file_renamed_to_xml(): void
    {
        $maintenance = $this->ownedMaintenance();

        $this->postJson('/api/v1/invoices/upload', [
            'file' => $this->uploadWithContent("MZ\x90\x00binário", 'nota.xml'),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * Uma NF-e sem a declaração `<?xml ?>` chega como text/plain; o PDF e o XML de verdade
     * continuam aceitos (ver também test_accepts_xml_with_text_plain_mime).
     */
    public function test_accepts_the_real_nfe_fixtures(): void
    {
        $maintenance = $this->ownedMaintenance();

        foreach (['divesa_nfe_80000.pdf' => 'nota.pdf', 'troia_nfe_4250.xml' => 'nota.xml'] as $fixture => $clientName) {
            $this->postJson('/api/v1/invoices/upload', [
                'file' => new UploadedFile(base_path('tests/fixtures/invoices/'.$fixture), $clientName, null, null, true),
                'maintenance_id' => $maintenance->id,
                'invoice_type' => 'general',
            ])->assertCreated();
        }

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_failed_invoice_insert_leaves_no_file_in_storage(): void
    {
        $maintenance = $this->ownedMaintenance();

        Invoice::creating(function (): void {
            throw new \RuntimeException('insert failed');
        });

        $this->postJson('/api/v1/invoices/upload', [
            'file' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertStatus(500);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    public function test_cannot_attach_an_invoice_to_an_item_of_another_maintenance(): void
    {
        $maintenance = $this->ownedMaintenance();
        $otherMaintenance = $this->ownedMaintenance();
        $otherItem = MaintenanceItem::factory()->create(['maintenance_id' => $otherMaintenance->id]);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $otherItem->id,
            'invoice_type' => 'item',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['maintenance_item_id']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    public function test_rejects_a_maintenance_item_id_that_is_not_a_number(): void
    {
        $maintenance = $this->ownedMaintenance();

        $this->postJson('/api/v1/invoices/upload', [
            'file' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => 'abc',
            'invoice_type' => 'item',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['maintenance_item_id']);
    }

    /**
     * A manutenção de outro tenant responde 403 antes de qualquer 422, mesmo quando o
     * arquivo enviado também é inválido.
     */
    public function test_other_tenant_maintenance_is_forbidden_before_validation(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $otherUser->id,
            'tenant_id' => $otherUser->tenant_id,
        ]);

        $this->postJson('/api/v1/invoices/upload', [
            'file' => UploadedFile::fake()->create('nota.txt', 100),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertForbidden();
    }

    /**
     * O limite de tamanho vem antes da checagem de conteúdo, com `bail`, então o arquivo
     * grande é recusado sem ser lido nem percorrido pelo parser.
     */
    public function test_the_size_limit_rejects_a_big_file_before_the_content_check(): void
    {
        $maintenance = $this->ownedMaintenance();

        $response = $this->postJson('/api/v1/invoices/upload', [
            'file' => $this->uploadWithContent(str_repeat('lixo binario ', 900000), 'nota.xml'),
            'maintenance_id' => $maintenance->id,
            'invoice_type' => 'general',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $messages = $response->json('errors.file');
        $this->assertNotEmpty($messages);

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('não é um XML', $message);
        }
    }

    /**
     * Um Error (e não Exception) vindo do insert escapava do catch e deixava o órfão.
     */
    public function test_an_error_from_the_insert_still_removes_the_file(): void
    {
        $maintenance = $this->ownedMaintenance();

        Invoice::creating(function (): void {
            throw new \TypeError('insert quebrou');
        });

        $this->expectException(\TypeError::class);

        try {
            $this->withoutExceptionHandling()
                ->postJson('/api/v1/invoices/upload', [
                    'file' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
                    'maintenance_id' => $maintenance->id,
                    'invoice_type' => 'general',
                ]);
        } finally {
            $this->assertDatabaseCount('invoices', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
        }
    }

    private function ownedMaintenance(): Maintenance
    {
        $user = $this->app->make('auth')->user() ?? $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    private function uploadWithContent(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-upload-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    }
}
