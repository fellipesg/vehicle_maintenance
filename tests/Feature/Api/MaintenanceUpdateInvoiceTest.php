<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use App\Rules\InvoiceFile;
use App\Services\Invoice\InvoiceUploadProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `PUT /api/v1/maintenances/{id}` validava `invoices` (array com teto de 10, cada arquivo lido
 * inteiro por App\Rules\InvoiceFile) e descartava o campo num `unset` logo depois: o cliente
 * pagava o parsing, recebia 200 e nada ficava anexado. Com oficina da rede escolhida a
 * RequiresInvoiceWhenWorkshopAssigned ainda exigia a nota que seria jogada fora.
 *
 * Agora o update anexa pelo mesmo InvoiceUploadProcessor do store, na ordem do portal web:
 * arquivos no storage antes de gravar a OS, linhas depois dos itens, parsing no fim e nenhum
 * arquivo órfão nos caminhos de falha.
 */
class MaintenanceUpdateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_attaches_the_invoices_it_was_sent(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'description' => 'Revisão com nota',
            'invoices' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance updated successfully')
            ->assertJsonCount(1, 'data.invoices');

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'file_name' => 'nota.pdf',
            'invoice_type' => 'general',
        ]);
        $this->assertSame('Revisão com nota', $maintenance->fresh()->description);
        $this->assertCount(1, Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * O caminho do store inteiro, não só o insert: a NF-e anexada na edição também preenche os
     * itens da OS que ainda não tem nenhum, e a contagem volta no envelope.
     */
    public function test_update_imports_the_nfe_items_of_the_invoice_it_attaches(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();

        $file = new UploadedFile(
            base_path('tests/fixtures/invoices/troia_nfe_4250.xml'),
            'troia_nfe.xml',
            'application/xml',
            null,
            true,
        );

        $this->put("/api/v1/maintenances/{$maintenance->id}", ['invoices' => [$file]])
            ->assertOk()
            ->assertJsonPath('parsed_items_count', 5);

        $this->assertDatabaseCount('maintenance_items', 5);
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'invoice_number' => '4250',
        ]);
    }

    public function test_update_adds_to_the_invoices_the_os_already_had(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();
        Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => 'invoices/antiga.pdf',
            'file_name' => 'antiga.pdf',
        ]);

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'invoices' => [UploadedFile::fake()->create('nova.pdf', 20, 'application/pdf')],
        ])->assertOk()->assertJsonCount(2, 'data.invoices');

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_update_without_the_field_leaves_the_existing_invoices_alone(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();
        $invoice = Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => 'invoices/antiga.pdf',
            'file_name' => 'antiga.pdf',
        ]);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['description' => 'Só o texto'])
            ->assertOk()
            ->assertJsonCount(1, 'data.invoices');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    /**
     * O teto de InvoiceFile::MAX_FILES_PER_REQUEST entrou no PR #71 num campo ignorado. Agora ele
     * vale no caminho que grava, com a mensagem em pt-BR do `custom.invoices.max`.
     */
    public function test_update_refuses_more_invoices_than_the_limit(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST + 1),
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.invoices.0',
                'Envie no máximo '.InvoiceFile::MAX_FILES_PER_REQUEST.' notas fiscais por vez. Se precisar anexar mais, salve e envie o resto depois.',
            );

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'invoices' => $this->invoicePdfs(InvoiceFile::MAX_FILES_PER_REQUEST),
        ])->assertOk();

        $this->assertDatabaseCount('invoices', InvoiceFile::MAX_FILES_PER_REQUEST);
    }

    /**
     * A OS editada está commitada e não volta atrás (os inserts de nota ficam fora de transação,
     * em .ai/rules/invoices.md), então a nota que não entrou é nomeada e o arquivo sem linha sai
     * do storage em vez de ficar órfão.
     */
    public function test_update_keeps_the_os_and_names_the_invoice_when_the_insert_fails(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();

        Invoice::creating(function (): void {
            throw new \RuntimeException('insert failed');
        });

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'description' => 'Edição que fica salva',
            'invoices' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Maintenance updated, but some invoice files were not attached.')
            ->assertJsonPath('invoice_failures', ['nota.pdf']);

        $this->assertSame('Edição que fica salva', $maintenance->fresh()->description);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('invoices'));
    }

    /**
     * As disks rodam com `throw => false`, então a recusa do S3/R2 volta como `null` do storeFile.
     * Aqui nada foi gravado ainda: a requisição falha inteira e a OS fica como estava, igual ao store.
     */
    public function test_update_fails_before_touching_the_os_when_the_storage_refuses_the_file(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();
        $originalDescription = $maintenance->description;

        $this->partialMock(
            InvoiceUploadProcessor::class,
            fn ($mock) => $mock->shouldReceive('storeFile')->andReturn(null),
        );

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'description' => 'Edição que não deve valer',
            'invoices' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
        ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Unable to store the invoice files. No maintenance was updated.');

        $this->assertSame($originalDescription, $maintenance->fresh()->description);
        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * A contradição que motivou a correção: vincular oficina da rede exige a nota
     * (RequiresInvoiceWhenWorkshopAssigned), e antes essa nota exigida era descartada.
     */
    public function test_update_linking_a_registered_workshop_attaches_the_invoice_it_demands(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();
        $workshop = Workshop::factory()->create();

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'workshop_id' => $workshop->id,
            'invoices' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.invoices.0',
                'Informe ao menos uma nota fiscal (PDF ou XML) ao vincular uma oficina cadastrada.',
            );

        $this->assertNull($maintenance->fresh()->workshop_id);

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'workshop_id' => $workshop->id,
            'invoices' => [UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')],
        ])->assertOk()->assertJsonCount(1, 'data.invoices');

        $this->assertSame($workshop->id, $maintenance->fresh()->workshop_id);
        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'file_name' => 'nota.pdf',
        ]);
    }

    /**
     * A nota já anexada satisfaz a exigência: reenviar a mesma oficina não pede outra.
     */
    public function test_update_does_not_demand_another_invoice_when_the_os_already_has_one(): void
    {
        Storage::fake('public');
        [, $maintenance] = $this->ownedMaintenance();
        $workshop = Workshop::factory()->create();
        Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => 'invoices/antiga.pdf',
            'file_name' => 'antiga.pdf',
        ]);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'workshop_id' => $workshop->id,
            'invoices' => [],
        ])->assertOk();

        $this->assertSame($workshop->id, $maintenance->fresh()->workshop_id);
    }

    /**
     * No portal da oficina a nota nunca é exigida, mas a que vier é anexada igual.
     */
    public function test_workshop_portal_update_attaches_the_invoice_without_demanding_one(): void
    {
        Storage::fake('public');
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $this->actingAsApiUser($workshopUser);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $workshopUser->tenant_id,
            'workshop_id' => $workshop->id,
        ]);

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", ['invoices' => []])->assertOk();

        $this->put("/api/v1/maintenances/{$maintenance->id}", [
            'invoices' => [UploadedFile::fake()->create('os.pdf', 20, 'application/pdf')],
        ])->assertOk()->assertJsonCount(1, 'data.invoices');

        $this->assertDatabaseHas('invoices', [
            'maintenance_id' => $maintenance->id,
            'file_name' => 'os.pdf',
        ]);
    }

    /**
     * O mesmo `unset` tira `items` e `general_warranty_template_id` do update, e esses dois são
     * sincronizados depois pelos traits a partir do próprio request — ao contrário de `invoices`,
     * que não tinha quem o lesse. Este teste prende os dois numa edição só.
     */
    public function test_update_still_syncs_the_items_and_the_general_warranty_it_was_sent(): void
    {
        Storage::fake('public');
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->workshop;
        $this->actingAsApiUser($workshopUser);

        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $workshopUser->tenant_id,
            'workshop_id' => $workshop->id,
        ]);
        $template = WarrantyTemplate::factory()->forWorkshop($workshop)->orderScope()->create();

        $this->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'general_warranty_template_id' => $template->id,
            'items' => [
                ['name' => 'Filtro de óleo', 'quantity' => 1, 'unit_price' => '45.90', 'total_price' => '45.90'],
            ],
        ])->assertOk()->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('maintenance_items', [
            'maintenance_id' => $maintenance->id,
            'name' => 'Filtro de óleo',
        ]);
        $this->assertDatabaseHas('maintenance_warranties', [
            'maintenance_id' => $maintenance->id,
            'scope' => 'order',
            'warranty_template_id' => $template->id,
        ]);
    }

    /**
     * @return array{User, Maintenance}
     */
    private function ownedMaintenance(): array
    {
        $user = $this->actingAsApiUser();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);
        $maintenance = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return [$user, $maintenance];
    }

    /**
     * @return list<UploadedFile>
     */
    private function invoicePdfs(int $count): array
    {
        return array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->create("nota-{$index}.pdf", 10, 'application/pdf'),
            range(1, $count),
        );
    }
}
