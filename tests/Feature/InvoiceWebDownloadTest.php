<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * A nota fiscal do portal passa pela rota invoices.show (InvoicePolicy::view), nunca pelo caminho
 * do arquivo no storage.
 */
class InvoiceWebDownloadTest extends TestCase
{
    use BuildsOwnerlessRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_owner_opens_the_invoice_of_their_maintenance(): void
    {
        $owner = User::factory()->asUser()->create();
        $invoice = $this->invoiceFor($this->maintenanceOf($owner));

        $response = $this->actingAs($owner)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString($invoice->file_name, (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('content-security-policy'));
    }

    public function test_xml_invoice_is_downloaded_instead_of_opened_in_the_tab(): void
    {
        $owner = User::factory()->asUser()->create();
        $invoice = $this->invoiceFor($this->maintenanceOf($owner), 'nfe.xml', '<nfeProc/>');

        $response = $this->actingAs($owner)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml');
        $response->assertDownload('nfe.xml');
    }

    public function test_another_account_cannot_open_the_invoice(): void
    {
        $invoice = $this->invoiceFor($this->maintenanceOf(User::factory()->asUser()->create()));
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)
            ->get(route('invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $invoice = $this->invoiceFor($this->maintenanceOf(User::factory()->asUser()->create()));

        $this->get(route('invoices.show', $invoice))->assertRedirect(route('login'));
    }

    public function test_unverified_owner_cannot_open_pending_attachment_of_ownerless_record(): void
    {
        $workshopUser = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $maintenance = $this->ownerlessRecord($workshopUser, $vehicle);
        $invoice = $this->invoiceFor($maintenance);
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice))
            ->assertForbidden();

        $this->actingAs($workshopUser)
            ->get(route('invoices.show', $invoice))
            ->assertOk();
    }

    public function test_missing_file_returns_not_found(): void
    {
        $owner = User::factory()->asUser()->create();
        $invoice = Invoice::factory()->create([
            'maintenance_id' => $this->maintenanceOf($owner)->id,
            'file_path' => 'invoices/sumiu.pdf',
            'file_name' => 'sumiu.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice))
            ->assertNotFound();
    }

    public function test_maintenance_detail_links_to_the_route_not_to_the_storage_path(): void
    {
        $owner = User::factory()->asUser()->create();
        $maintenance = $this->maintenanceOf($owner);
        $invoice = $this->invoiceFor($maintenance);

        $response = $this->actingAs($owner)->get(route('user.maintenances.show', $maintenance));

        $response->assertOk();
        $response->assertSee(route('invoices.show', $invoice), false);
        $response->assertDontSee($invoice->file_path, false);
    }

    private function maintenanceOf(User $owner): Maintenance
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        return Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->fresh()->tenant_id,
        ]);
    }

    private function invoiceFor(
        Maintenance $maintenance,
        string $fileName = 'nota_fiscal.pdf',
        string $contents = '%PDF-1.4 teste',
    ): Invoice {
        $path = 'invoices/'.pathinfo($fileName, PATHINFO_FILENAME).'-armazenada.'.pathinfo($fileName, PATHINFO_EXTENSION);
        Storage::disk('public')->put($path, $contents);

        return Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $path,
            'file_name' => $fileName,
        ]);
    }
}
