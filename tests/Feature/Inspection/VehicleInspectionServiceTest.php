<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Services\Inspection\VehicleInspectionService;
use App\Support\Inspection\InspectionFindings;
use App\Support\Maintenance\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * As duas garantias do laudo e o que ele deliberadamente **não** faz com o hodômetro.
 */
class VehicleInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleInspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VehicleInspectionService::class);
    }

    public function test_an_inspection_is_created_with_a_verdict_findings_and_a_public_ldo_code(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        $inspection = $this->service->create($vehicle, $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'odometer_km' => 48_000,
            'verdict' => InspectionVerdict::ApprovedWithRestrictions->value,
            'findings' => [
                'paint' => ['status' => 'attention', 'note' => '  Repintura na porta direita.  '],
            ],
            'summary' => '  Veículo apto, com ressalva de pintura.  ',
            'valid_until' => '2027-02-10',
        ]);

        $this->assertSame(InspectionVerdict::ApprovedWithRestrictions, $inspection->verdict);
        $this->assertSame(48_000, $inspection->odometer_km);
        $this->assertSame('Veículo apto, com ressalva de pintura.', $inspection->summary);
        $this->assertSame($workshop->id, $inspection->workshop_id);
        $this->assertSame($workshop->name, $inspection->workshop_name);

        // O mapa é completo mesmo quando o vistoriador mandou um eixo só.
        $this->assertSame(InspectionFinding::values(), array_keys($inspection->findings));
        $this->assertSame('Repintura na porta direita.', $inspection->findings['paint']['note']);
        $this->assertSame(
            InspectionFindingStatus::NotAssessed->value,
            $inspection->findings['recall']['status'],
        );

        $this->assertNotNull($inspection->verification_code);
        $this->assertStringStartsWith(VerificationCode::INSPECTION_PREFIX.'-', $inspection->verification_code);
        $this->assertSame(
            $inspection->verification_code,
            VerificationCode::normalize($inspection->verification_code, VerificationCode::INSPECTION_PREFIX),
        );
        $this->assertStringContainsString('/laudo/'.$inspection->verification_code, (string) $inspection->verificationUrl());
    }

    public function test_an_approved_verdict_cannot_hide_a_failed_axis(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        try {
            $this->service->create($vehicle, $workshop, $user, [
                'inspected_at' => '2026-11-10',
                'verdict' => InspectionVerdict::Approved->value,
                'findings' => [
                    'identification' => ['status' => 'fail', 'note' => 'Chassi remarcado.'],
                ],
            ]);

            $this->fail('Um laudo "Aprovado" com eixo reprovado deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('verdict', $e->errors());
            $this->assertStringContainsString('Chassi e numeração do motor', $e->errors()['verdict'][0]);
        }

        $this->assertSame(0, VehicleInspection::query()->count());
    }

    public function test_approved_with_restrictions_and_rejected_may_carry_a_failed_axis(): void
    {
        [$user, $workshop] = $this->issuer();

        foreach ([InspectionVerdict::ApprovedWithRestrictions, InspectionVerdict::Rejected] as $verdict) {
            $inspection = $this->service->create($this->vehicle(), $workshop, $user, [
                'inspected_at' => '2026-11-10',
                'verdict' => $verdict->value,
                'findings' => ['identification' => ['status' => 'fail']],
            ]);

            $this->assertSame($verdict, $inspection->verdict);
        }
    }

    public function test_an_implausible_odometer_reading_is_refused(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        try {
            $this->service->create($vehicle, $workshop, $user, [
                'inspected_at' => '2026-11-10',
                'odometer_km' => 900_000,
                'verdict' => InspectionVerdict::Approved->value,
            ]);

            $this->fail('Uma leitura de 900.000 km num carro do ano deveria ser recusada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('odometer_km', $e->errors());
        }
    }

    /**
     * O ponto da modelagem: a vistoria é o único ator com licença para ler **abaixo** do histórico,
     * porque é esse o achado. Recusar aqui proibiria o laudo de relatar adulteração de hodômetro.
     */
    public function test_a_reading_below_an_earlier_order_is_accepted_because_that_is_the_finding(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'kilometers' => 90_000,
            'maintenance_date' => '2026-03-01',
        ]);

        $inspection = $this->service->create($vehicle->fresh(), $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'odometer_km' => 40_000,
            'verdict' => InspectionVerdict::Rejected->value,
            'findings' => [
                'odometer' => ['status' => 'fail', 'note' => 'Leitura abaixo de registro anterior.'],
            ],
        ]);

        $this->assertSame(40_000, $inspection->odometer_km);
        $this->assertSame([InspectionFinding::Odometer], $inspection->failingFindings());
    }

    public function test_the_odometer_axis_cannot_say_nothing_is_wrong_when_the_reading_contradicts_the_history(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'kilometers' => 90_000,
            'maintenance_date' => '2026-03-01',
        ]);

        try {
            $this->service->create($vehicle->fresh(), $workshop, $user, [
                'inspected_at' => '2026-11-10',
                'odometer_km' => 40_000,
                'verdict' => InspectionVerdict::Approved->value,
                'findings' => ['odometer' => ['status' => 'ok']],
            ]);

            $this->fail('"Sem apontamento" no hodômetro contra o próprio número do laudo deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('findings.odometer.status', $e->errors());
            $message = $e->errors()['findings.odometer.status'][0];
            $this->assertStringContainsString('40000 km', $message);
            $this->assertStringContainsString('90000 km', $message);
            $this->assertStringContainsString('01/03/2026', $message);
        }
    }

    /**
     * Guardar a transição, não o estado: um laudo gravado antes da regra (ou por outro caminho) não
     * pode trancar a própria tela de edição.
     */
    public function test_an_edit_that_does_not_touch_the_claim_is_not_re_judged(): void
    {
        $vehicle = $this->vehicle();

        // Laudo incoerente que o serviço nunca criaria: é o dado legado.
        $inspection = VehicleInspection::factory()
            ->verdict(InspectionVerdict::Approved)
            ->failing(InspectionFinding::Structural)
            ->create(['vehicle_id' => $vehicle->id, 'odometer_km' => 50_000]);

        $updated = $this->service->update($inspection, ['inspected_at' => '2026-11-11']);

        $this->assertSame('2026-11-11', $updated->inspected_at->toDateString());
        $this->assertSame(InspectionVerdict::Approved, $updated->verdict);
    }

    public function test_changing_the_verdict_does_not_re_judge_an_untouched_odometer(): void
    {
        $vehicle = $this->vehicle();

        // Hodômetro implausível já gravado: mexer no veredito não pode exigir consertá-lo antes.
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 900_000,
        ]);

        $updated = $this->service->update($inspection, [
            'verdict' => InspectionVerdict::Rejected->value,
        ]);

        $this->assertSame(InspectionVerdict::Rejected, $updated->verdict);
        $this->assertSame(900_000, $updated->odometer_km);
    }

    public function test_an_edit_that_moves_the_reading_is_judged_again(): void
    {
        $vehicle = $this->vehicle();
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 50_000,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->update($inspection, ['odometer_km' => 900_000]);
    }

    /**
     * A vistoria grava o que leu e **não** move o número do veículo. `current_kilometers` é um
     * max() recalculado do zero a partir de `maintenances` em dez chamadores: escrever ali faria o
     * próximo recálculo apagar o número em silêncio, e o max() nunca saberia relatar uma leitura
     * *abaixo* do histórico, que é a informação mais valiosa do laudo.
     */
    public function test_the_inspection_does_not_move_the_vehicle_current_kilometers(): void
    {
        [$user, $workshop] = $this->issuer();
        $vehicle = $this->vehicle();

        $before = $vehicle->current_kilometers;

        $this->service->create($vehicle, $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'odometer_km' => 120_000,
            'verdict' => InspectionVerdict::Approved->value,
        ]);

        $this->assertSame($before, $vehicle->fresh()->current_kilometers);
    }

    public function test_the_report_pdf_is_stored_under_a_random_name_and_replaces_the_previous_file(): void
    {
        Storage::fake('public');

        [$user, $workshop] = $this->issuer();
        $inspection = $this->service->create($this->vehicle(), $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'verdict' => InspectionVerdict::Approved->value,
        ]);

        $first = UploadedFile::fake()->create('laudo Maria Silva 123.456.789-00.pdf', 20, 'application/pdf');
        $inspection = $this->service->attachReport($inspection, $first);

        $firstPath = $inspection->report_path;

        $this->assertStringStartsWith('inspections/', (string) $firstPath);
        $this->assertStringEndsWith('.pdf', (string) $firstPath);
        $this->assertStringNotContainsString('Maria', (string) $firstPath);
        $this->assertStringNotContainsString('456', (string) $firstPath);
        $this->assertSame('laudo Maria Silva 123.456.789-00.pdf', $inspection->report_original_name);
        Storage::disk('public')->assertExists((string) $firstPath);

        $second = UploadedFile::fake()->create('laudo-corrigido.pdf', 20, 'application/pdf');
        $inspection = $this->service->attachReport($inspection, $second);

        $this->assertNotSame($firstPath, $inspection->report_path);
        Storage::disk('public')->assertExists((string) $inspection->report_path);
        Storage::disk('public')->assertMissing((string) $firstPath);
    }

    public function test_detaching_the_report_clears_the_columns_and_the_file(): void
    {
        Storage::fake('public');

        [$user, $workshop] = $this->issuer();
        $inspection = $this->service->create($this->vehicle(), $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'verdict' => InspectionVerdict::Approved->value,
        ]);

        $inspection = $this->service->attachReport(
            $inspection,
            UploadedFile::fake()->create('laudo.pdf', 20, 'application/pdf'),
        );
        $path = (string) $inspection->report_path;

        $inspection = $this->service->detachReport($inspection);

        $this->assertNull($inspection->report_path);
        $this->assertNull($inspection->report_original_name);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_report_that_is_not_a_pdf_is_refused(): void
    {
        Storage::fake('public');

        [$user, $workshop] = $this->issuer();
        $inspection = $this->service->create($this->vehicle(), $workshop, $user, [
            'inspected_at' => '2026-11-10',
            'verdict' => InspectionVerdict::Approved->value,
        ]);

        try {
            $this->service->attachReport($inspection, UploadedFile::fake()->image('laudo.png'));

            $this->fail('Um PNG não deveria ser aceito como laudo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('report', $e->errors());
        }

        $this->assertNull($inspection->fresh()->report_path);
    }

    public function test_an_unknown_verdict_is_refused_before_anything_is_written(): void
    {
        [$user, $workshop] = $this->issuer();

        try {
            $this->service->create($this->vehicle(), $workshop, $user, [
                'inspected_at' => '2026-11-10',
                'verdict' => 'quase_aprovado',
            ]);

            $this->fail('Veredito desconhecido deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('verdict', $e->errors());
        }

        $this->assertSame(0, VehicleInspection::query()->count());
    }

    public function test_the_findings_map_is_normalized_on_update_too(): void
    {
        $inspection = VehicleInspection::factory()->create(['vehicle_id' => $this->vehicle()->id]);

        $updated = $this->service->update($inspection, [
            'findings' => ['recall' => ['status' => 'attention', 'note' => 'Recall de airbag aberto.']],
        ]);

        $this->assertSame(InspectionFinding::values(), array_keys($updated->findings));
        $this->assertSame(
            InspectionFindings::normalize($updated->findings),
            $updated->findings,
        );
    }

    /**
     * @return array{0: User, 1: Workshop}
     */
    private function issuer(): array
    {
        // UserFactory::configure() já cria a oficina aprovada da conta; criar outra deixaria duas
        // linhas com o mesmo user_id e `$user->workshop` apontaria para a errada.
        $user = User::factory()->asWorkshop()->create();

        return [$user->fresh(), $user->fresh()->workshop];
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'year' => (int) now()->year,
            'current_kilometers' => 10_000,
            'odometer_at_registration' => 10_000,
        ]);
    }
}
