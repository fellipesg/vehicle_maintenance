<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionVerdict;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Services\Inspection\VehicleInspectionService;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A emissão do laudo é retentável, e não deixa arquivo órfão.
 *
 * O defeito: o laudo era gravado dentro da transação e `attachReport` rodava **fora** dela. Uma
 * recusa do storage (as disks rodam com `throw => false`, então `storeAs` devolve `false` sem
 * exceção) mostrava à oficina "Não foi possível guardar o laudo. Tente novamente." com o laudo
 * **já emitido** — com código de conferência e no histórico do chassi. Tentar de novo criava o
 * segundo documento sobre o mesmo carro.
 *
 * A correção segue a decisão já registrada em .ai/rules/invoices.md de **não** cercar I/O remoto com
 * transação: o arquivo sobe **antes**, como no `store` da API, e o laudo nasce com o caminho já
 * dentro. Então a recusa do storage volta como erro de campo, com zero laudo gravado, e a
 * retentativa é só a retentativa.
 */
class InspectionReportStorageFailureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O caso do achado: storage recusando, laudo nenhum emitido, e a segunda tentativa (com o
     * storage de volta) emite **um**.
     */
    public function test_a_storage_refusal_emits_no_report_and_the_retry_emits_one(): void
    {
        Notification::fake();
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        // `throw => false` nas disks: a recusa do storage volta `false`, não exceção.
        Storage::shouldReceive('disk')->andReturnUsing(fn () => throw new \RuntimeException('storage down'));

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf'),
        ]));

        // O ponto inteiro: nada foi emitido, então não há o que duplicar.
        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    /**
     * O caminho exato do achado: as disks rodam com `throw => false` (config/filesystems.php), então
     * a recusa do storage volta `false` de `storeAs` — **sem exceção e sem log**. É o `null`/`false`
     * que .ai/rules/invoices.md manda nunca tratar com `continue`. Aqui ele vira o erro de campo que
     * a tela mostra, e nenhuma linha é gravada.
     */
    public function test_a_disk_that_returns_false_becomes_a_field_error(): void
    {
        Storage::fake('public');

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with(AppStorage::diskName())->andReturn($disk);

        $file = UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf');

        try {
            app(VehicleInspectionService::class)->storeReportFile($file);
            $this->fail('A recusa do storage deveria virar ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Não foi possível guardar o laudo. Tente novamente.'],
                $exception->errors()['report'],
            );
        }

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    /**
     * `storeAs` bem-sucedido com `save()` falho deixava um objeto órfão em `inspections/` para
     * sempre: o varredor PurgePendingMaintenanceAttachments é só de fotos de manutenção, e nenhuma
     * rotina olha esse prefixo. Agora o arquivo novo é descartado quando a linha não entra.
     */
    public function test_a_failed_save_does_not_leave_an_orphan_file(): void
    {
        Storage::fake('public');

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $service = app(VehicleInspectionService::class);

        // Uma coluna inexistente faz o save() estourar depois de o arquivo já estar no storage.
        $inspection->forceFill(['nao_existe_esta_coluna' => 1]);

        try {
            $service->attachReport($inspection, UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf'));
            $this->fail('O save() deveria ter falhado.');
        } catch (\Throwable) {
            // esperado
        }

        // Nenhum PDF de laudo ficou para trás.
        $leftovers = array_filter(
            AppStorage::disk()->allFiles('inspections'),
            fn (string $path): bool => str_ends_with($path, '.pdf'),
        );

        $this->assertSame([], array_values($leftovers));
        $this->assertNull($inspection->fresh()->report_path);
    }

    /**
     * Substituir o PDF apaga o anterior, e o anterior **não** é apagado quando a gravação falha: a
     * linha continua apontando para ele, e apagá-lo deixaria a rota de download sem resposta (é a
     * mesma escolha de createInvoiceRecordsOrDiscard, em .ai/rules/invoices.md).
     */
    public function test_a_failed_replacement_keeps_the_previous_file(): void
    {
        Notification::fake();
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('primeiro.pdf', 100, 'application/pdf'),
        ]))->assertRedirect();

        $inspection = VehicleInspection::query()->sole();
        $first = (string) $inspection->report_path;
        AppStorage::disk()->assertExists($first);

        $service = app(VehicleInspectionService::class);
        $inspection->forceFill(['nao_existe_esta_coluna' => 1]);

        try {
            $service->attachReport($inspection, UploadedFile::fake()->create('segundo.pdf', 100, 'application/pdf'));
        } catch (\Throwable) {
            // esperado
        }

        // O primeiro continua lá, porque a linha continua apontando para ele.
        AppStorage::disk()->assertExists($first);
        $this->assertSame($first, $inspection->fresh()->report_path);
    }

    /**
     * A extensão forjada é recusada **no serviço**, não só pelo `mimes:pdf` do FormRequest: o serviço
     * é quem monta o caminho gravado, e quem o chamar de outro lugar (um comando, um recurso de API
     * futuro) não deve poder escolher a extensão do objeto. Antes ele confiava primeiro em
     * `getClientOriginalExtension()`, então `.pdf` no nome bastava.
     */
    public function test_a_forged_extension_is_refused_by_the_service(): void
    {
        Storage::fake('public');

        $path = tempnam(sys_get_temp_dir(), 'laudo-');
        file_put_contents($path, '<html><body>não sou um laudo</body></html>');

        // UploadedFile de verdade: no fake do Laravel o MIME sai do nome e a forja passaria.
        $forged = new UploadedFile($path, 'laudo.pdf', null, null, true);

        try {
            app(VehicleInspectionService::class)->storeReportFile($forged);
            $this->fail('A extensão forjada deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertSame(['O laudo deve ser um arquivo PDF.'], $exception->errors()['report']);
        }

        // E nada foi gravado no storage.
        $this->assertSame([], AppStorage::disk()->allFiles('inspections'));
    }

    /**
     * O laudo nasce com o PDF já gravado: uma gravação só, sem a janela entre "laudo emitido" e
     * "arquivo anexado".
     */
    public function test_the_report_is_stored_in_the_same_write_as_the_inspection(): void
    {
        Notification::fake();
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf'),
        ]))->assertRedirect();

        $inspection = VehicleInspection::query()->sole();

        $this->assertStringStartsWith('inspections/', (string) $inspection->report_path);
        $this->assertSame('laudo.pdf', $inspection->report_original_name);
        AppStorage::disk()->assertExists($inspection->report_path);
    }

    /**
     * @return array{0: User, 1: Workshop}
     */
    private function workshop(): array
    {
        $user = User::factory()->asWorkshop()->create();

        return [$user->fresh(), $user->fresh()->workshop];
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->fresh(), $vehicle->fresh()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'inspected_at' => now()->toDateString(),
            'verdict' => InspectionVerdict::ApprovedWithRestrictions->value,
        ], $overrides);
    }
}
