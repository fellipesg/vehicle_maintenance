<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Invoice\InvoiceItemSyncer;
use App\Services\Invoice\InvoiceParseFeedback;
use App\Services\Invoice\InvoiceParser;
use App\Services\Invoice\InvoiceUploadProcessor;
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

    public function test_store_rejects_a_file_renamed_to_pdf(): void
    {
        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [$this->uploadWithContent("#!/bin/sh\necho pwned\n", 'nota.pdf')],
            ]))
            ->assertSessionHasErrors(['invoices.0']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * O caminho web de atualização gravava o arquivo antes da linha e não o apagava
     * quando o insert falhava, deixando nota órfã no S3/R2. Hoje o insert que falha
     * também não estoura: a OS atualizada fica, e o usuário é avisado do que não entrou.
     */
    public function test_update_leaves_no_file_in_storage_when_the_invoice_insert_fails(): void
    {
        $maintenance = $this->createMaintenance();

        Invoice::creating(function (): void {
            throw new \RuntimeException('insert failed');
        });

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ], withPlate: false))
            ->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'OS atualizada.')
            ->assertSessionHas('error', $this->notAttachedMessageFor('troia_nfe_4250.xml'));

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * Os inserts das notas não estão numa transação (pooler do Neon), então a primeira
     * nota já está gravada quando a segunda falha. Apagar o arquivo dela deixaria a nota
     * apontando para o vazio, e a rota de download só poderia responder 404.
     */
    public function test_update_keeps_the_first_invoices_file_when_the_second_insert_fails(): void
    {
        $maintenance = $this->createMaintenance();

        $this->failOnInvoiceNumber(2);

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'invoices' => [
                    $this->fixture('troia_nfe_4250.xml', 'application/xml'),
                    $this->fixture('divesa_nfe_33893.xml', 'text/plain'),
                ],
            ], withPlate: false))
            ->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', $this->notAttachedMessageFor('divesa_nfe_33893.xml'));

        $this->assertEveryInvoiceStillHasItsFile(expectedCount: 1);
    }

    public function test_store_keeps_the_first_invoices_file_when_the_second_insert_fails(): void
    {
        $this->failOnInvoiceNumber(2);

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [
                    $this->fixture('troia_nfe_4250.xml', 'application/xml'),
                    $this->fixture('divesa_nfe_33893.xml', 'text/plain'),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEveryInvoiceStillHasItsFile(expectedCount: 1);
    }

    /**
     * O defeito central: com a OS e a primeira nota já commitadas, a exceção da segunda
     * virava 500. O usuário lia "deu erro" sobre uma OS que existia e reenviava o
     * formulário, criando a OS duplicada. Agora recebe a OS salva e uma mensagem que diz
     * qual arquivo não foi anexado.
     */
    public function test_store_keeps_the_os_and_names_the_invoice_that_was_not_attached(): void
    {
        $this->failOnInvoiceNumber(2);

        $response = $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [
                    $this->fixture('troia_nfe_4250.xml', 'application/xml'),
                    $this->fixture('divesa_nfe_33893.xml', 'text/plain'),
                ],
            ]));

        $maintenance = Maintenance::firstOrFail();

        $response->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'OS registrada. Selo da oficina emitido: '.$maintenance->verification_code.'. 5 itens importados da NF-e.')
            ->assertSessionHas('error', $this->notAttachedMessageFor('divesa_nfe_33893.xml'));

        $this->assertSame(1, Maintenance::query()->count());
        $this->assertStringNotContainsString('troia_nfe_4250.xml', (string) session('error'));
        $this->assertEveryInvoiceStillHasItsFile(expectedCount: 1);
        $this->assertCount(1, Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * A promessa da mensagem tem de valer: enviar a nota outra vez anexa a que faltava
     * na mesma OS, sem uma segunda OS para o mesmo serviço.
     */
    public function test_the_invoice_that_failed_can_be_sent_again_without_a_second_os(): void
    {
        $this->failOnInvoiceNumber(2);

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [
                    $this->fixture('troia_nfe_4250.xml', 'application/xml'),
                    $this->fixture('divesa_nfe_33893.xml', 'text/plain'),
                ],
            ]))
            ->assertSessionHas('error');

        $maintenance = Maintenance::firstOrFail();

        $this->actingAs($this->workshopUser)
            ->put(route('workshop.maintenances.update', $maintenance), $this->osPayload([
                'invoices' => [$this->fixture('divesa_nfe_33893.xml', 'text/plain')],
            ], withPlate: false))
            ->assertRedirect(route('workshop.maintenances.show', $maintenance))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame(1, Maintenance::query()->count());
        $this->assertEveryInvoiceStillHasItsFile(expectedCount: 2);
        $this->assertSame(
            ['divesa_nfe_33893.xml', 'troia_nfe_4250.xml'],
            Invoice::query()->orderBy('file_name')->pluck('file_name')->all(),
        );
    }

    /**
     * A falha vale pelas seguintes, então a mensagem nomeia todas as que ficaram de fora.
     */
    public function test_store_names_every_invoice_left_out_when_the_second_of_three_fails(): void
    {
        $this->failOnInvoiceNumber(2);

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [
                    $this->fixture('troia_nfe_4250.xml', 'application/xml'),
                    $this->fixture('divesa_nfe_33893.xml', 'text/plain'),
                    $this->fixture('lemans_nfe_1940.pdf', 'application/pdf'),
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', InvoiceParseFeedback::notAttachedMessage([
                'divesa_nfe_33893.xml',
                'lemans_nfe_1940.pdf',
            ]));

        $this->assertSame(1, Maintenance::query()->count());
        $this->assertEveryInvoiceStillHasItsFile(expectedCount: 1);
        $this->assertCount(1, Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * Último caminho silencioso do fluxo: as disks rodam com throw => false, então uma
     * falha de gravação no S3/R2 voltava false, o arquivo era descartado sem exceção e a
     * OS nascia sem a nota, sem nada na tela nem no log.
     */
    public function test_store_does_not_fail_in_silence_when_the_storage_refuses_the_file(): void
    {
        $this->failToStoreEveryUpload();

        $this->actingAs($this->workshopUser)
            ->post(route('workshop.maintenances.store'), $this->osPayload([
                'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', $this->notAttachedMessageFor('troia_nfe_4250.xml'));

        $this->assertSame(1, Maintenance::query()->count());
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * Se a OS não nasce, nenhuma nota foi gravada e todos os arquivos vão embora.
     */
    public function test_store_discards_every_upload_when_the_maintenance_is_not_created(): void
    {
        Maintenance::creating(function (): void {
            throw new \RuntimeException('OS não foi criada');
        });

        $this->expectException(\RuntimeException::class);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->workshopUser)
                ->post(route('workshop.maintenances.store'), $this->osPayload([
                    'invoices' => [$this->fixture('troia_nfe_4250.xml', 'application/xml')],
                ]));
        } finally {
            $this->assertDatabaseCount('invoices', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
        }
    }

    private function notAttachedMessageFor(string $fileName): string
    {
        return InvoiceParseFeedback::notAttachedMessage([$fileName]);
    }

    /**
     * Storage que recusa toda gravação, como o S3/R2 fora do ar: storeFile devolve null.
     */
    private function failToStoreEveryUpload(): void
    {
        $this->app->instance(InvoiceUploadProcessor::class, new class($this->app->make(InvoiceParser::class), $this->app->make(InvoiceItemSyncer::class)) extends InvoiceUploadProcessor
        {
            public function storeFile(UploadedFile $file): ?string
            {
                return null;
            }
        });
    }

    private function failOnInvoiceNumber(int $position): void
    {
        $created = 0;

        Invoice::creating(function () use (&$created, $position): void {
            $created++;

            if ($created === $position) {
                throw new \RuntimeException('insert da nota falhou');
            }
        });
    }

    private function assertEveryInvoiceStillHasItsFile(int $expectedCount): void
    {
        $invoices = Invoice::all();

        $this->assertCount($expectedCount, $invoices);

        foreach ($invoices as $invoice) {
            $this->assertTrue(
                Storage::disk('public')->exists($invoice->file_path),
                "A nota #{$invoice->id} ficou apontando para um arquivo apagado.",
            );
        }
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

    private function uploadWithContent(string $content, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-web-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
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
