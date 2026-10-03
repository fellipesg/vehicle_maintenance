<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopMaintenanceInvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    private const SKIPPED_IMPORT_MESSAGE = 'Itens da NF-e não importados porque você já informou peças manualmente.';

    private User $workshopUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'NFE1A23',
            'current_kilometers' => 25000,
            'odometer_at_registration' => 25000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->workshopUser = User::factory()->asWorkshop()->create();
    }

    public function test_store_without_items_imports_the_items_of_the_nfe_xml(): void
    {
        $response = $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning')
            ->assertSessionMissing('info');

        $maintenance = Maintenance::firstOrFail();
        $response->assertSessionHas('success', 'OS registrada. Selo da oficina emitido: '.$maintenance->verification_code.'. 5 itens importados da NF-e.');
        $this->assertSame(5, $maintenance->items()->count());
        $this->assertDatabaseHas('maintenance_items', [
            'maintenance_id' => $maintenance->id,
            'name' => 'Óleo Lubrificante 0w20 Idemitsu',
            'part_number' => '2059',
        ]);
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '4250',
        ]);
    }

    public function test_store_with_manual_items_keeps_them_and_explains_why_the_xml_items_were_not_imported(): void
    {
        $response = $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'items' => [['name' => 'Troca de óleo', 'quantity' => 1]],
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info', self::SKIPPED_IMPORT_MESSAGE)
            ->assertSessionMissing('warning');

        $maintenance = Maintenance::firstOrFail();
        $response->assertSessionHas('success', 'OS registrada. Selo da oficina emitido: '.$maintenance->verification_code.'.');
        $this->assertSame(['Troca de óleo'], $maintenance->items()->pluck('name')->all());
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '4250',
        ]);
    }

    public function test_store_with_manual_items_and_a_readable_danfe_pdf_does_not_claim_the_pdf_failed(): void
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'items' => [['name' => 'Revisão de freios', 'quantity' => 1]],
                'invoices' => [$this->fixture('divesa_nfe_80000.pdf', 'application/pdf')],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info', self::SKIPPED_IMPORT_MESSAGE)
            ->assertSessionMissing('warning');

        $this->assertSame(1, Maintenance::firstOrFail()->items()->count());
    }

    public function test_store_with_an_unreadable_pdf_still_warns(): void
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning')
            ->assertSessionMissing('info');

        $this->assertStringContainsString('"nota.pdf" foi salvo', session('warning'));
    }

    public function test_update_with_an_empty_list_imports_the_items_of_the_nfe_xml(): void
    {
        $maintenance = $this->createMaintenance(['items' => [['name' => 'Item provisório', 'quantity' => 1]]]);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ], withPlate: false))
            ->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'OS atualizada. 5 itens importados da NF-e.');

        $names = $maintenance->items()->pluck('name');
        $this->assertCount(5, $names);
        $this->assertNotContains('Item provisório', $names);
    }

    public function test_update_with_items_keeps_them_when_an_xml_is_sent(): void
    {
        $maintenance = $this->createMaintenance(['items' => [['name' => 'Alinhamento', 'quantity' => 1]]]);
        $item = $maintenance->items()->firstOrFail();

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'items' => [['id' => $item->id, 'name' => 'Alinhamento', 'quantity' => 1]],
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ], withPlate: false))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'OS atualizada.')
            ->assertSessionHas('info', self::SKIPPED_IMPORT_MESSAGE);

        $this->assertSame([$item->id], $maintenance->items()->pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createMaintenance(array $extra = []): Maintenance
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload($extra))
            ->assertSessionHasNoErrors();

        return Maintenance::firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function osPayload(array $extra = [], bool $withPlate = true): array
    {
        $payload = [
            'maintenance_type' => 'Revisão',
            'maintenance_date' => '2026-02-15',
            'kilometers' => 25000,
            'service_category' => 'mechanical',
            ...$extra,
        ];

        if ($withPlate) {
            $payload['license_plate'] = 'NFE1A23';
        }

        return $payload;
    }

    private function fixture(string $fileName, string $mimeType): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/invoices/'.$fileName),
            $fileName,
            $mimeType,
            null,
            true,
        );
    }
}
