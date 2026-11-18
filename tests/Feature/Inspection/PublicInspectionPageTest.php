<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionFinding;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A página que o comprador abre com o carro na frente: /laudo/{código}, sem login.
 *
 * O que ela mostra tem de ser exatamente o que App\Support\Inspection\InspectionRedactor deixa
 * passar — veredito, data, hodômetro lido, validade, emissor e os oito status —, e nada do que ele
 * tira: resumo, observação de cada eixo, PDF e o texto da contestação. Rota mais generosa que a
 * redação é a mesma falha que .ai/rules/views.md descreve para o PDF.
 */
class PublicInspectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_reads_the_verdict_and_every_axis_without_the_prose(): void
    {
        $inspection = $this->inspection([
            'summary' => 'Carro em bom estado geral, com repintura na porta dianteira direita.',
        ], withRestrictions: true);

        $response = $this->get('/laudo/LDO-TEST-12')
            ->assertOk()
            ->assertSee('Verificação pública RevisaLog')
            ->assertSee('Laudo de vistoria cautelar')
            ->assertSee('Vistoria Confiável')
            ->assertSee('Aprovado com restrições')
            ->assertSee('Honda · Civic EX · 2022')
            ->assertSee('9BW••••••••••4251', false)
            ->assertSee('85.280 km')
            ->assertSee('data-copy-value="LDO-TEST-12"', false)
            ->assertSee('<meta name="robots" content="noindex">', false);

        // Os oito eixos, sempre todos: "não olhei" e "olhei e está bem" são respostas diferentes.
        foreach (InspectionFinding::cases() as $axis) {
            $response->assertSee('data-finding="'.$axis->value.'"', false);
            $response->assertSee($axis->label());
        }

        // A prosa não sai, e a página diz que ela existe em vez de fingir que o laudo não tem nada.
        $response->assertDontSee('Carro em bom estado geral');
        $response->assertDontSee('Repintura na porta dianteira direita.');
        $response->assertDontSee(route('inspections.report', $inspection), false);
        $response->assertSee('data-inspection-redacted', false);

        // O laudo não é o Selo da oficina: o anel teal afirma "eu fiz este serviço", e emprestá-lo a
        // "eu examinei e atesto" dilui a única primitiva de confiança do produto.
        $response->assertDontSee('prov-seal', false);
    }

    public function test_the_public_page_never_shows_the_plate_or_the_vehicle_odometer(): void
    {
        $this->inspection(vehicleAttributes: [
            'license_plate' => 'ABC1D23',
            'current_kilometers' => 150000,
        ]);

        $this->get('/laudo/LDO-TEST-12')
            ->assertOk()
            // A leitura do laudo sai; o hodômetro do veículo soma registros que a consulta pública
            // não lista (.ai/rules/public-lookup.md).
            ->assertSee('85.280 km')
            ->assertDontSee('150.000')
            ->assertDontSee('ABC1D23');
    }

    public function test_the_owner_reads_the_summary_and_opens_the_pdf(): void
    {
        $owner = User::factory()->asUser()->create();
        $inspection = $this->inspection(['summary' => 'Repintura na porta dianteira direita.']);
        $this->attachVehicleToUser($owner, $inspection->vehicle);
        $this->attachReport($inspection);

        $this->actingAs($owner->fresh())
            ->get('/laudo/LDO-TEST-12')
            ->assertOk()
            ->assertSee('Repintura na porta dianteira direita.')
            ->assertSee(route('inspections.report', $inspection), false)
            ->assertDontSee('data-inspection-redacted', false);
    }

    public function test_the_issuing_workshop_reads_the_whole_report_without_a_link_to_the_vehicle(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $inspection = $this->inspection(
            ['summary' => 'Repintura na porta dianteira direita.'],
            workshop: $workshopUser->fresh()->workshop,
        );
        $this->attachReport($inspection);

        $this->actingAs($workshopUser->fresh())
            ->get('/laudo/LDO-TEST-12')
            ->assertOk()
            ->assertSee('Repintura na porta dianteira direita.')
            ->assertSee(route('inspections.report', $inspection), false);
    }

    public function test_a_report_the_staff_suppressed_is_gone_for_the_world_and_answers_for_the_issuer(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = $workshopUser->fresh()->workshop;

        VehicleInspection::factory()
            ->hiddenByStaff()
            ->create([
                'vehicle_id' => Vehicle::factory()->create()->id,
                'workshop_id' => $workshop->id,
                'verification_code' => 'LDO-TEST-12',
            ]);

        $this->get('/laudo/LDO-TEST-12')->assertNotFound();

        // É a oficina que corrige o que assinou: esconder dela o próprio laudo trancaria a correção.
        $this->actingAs($workshopUser->fresh())
            ->get('/laudo/LDO-TEST-12')
            ->assertOk();
    }

    public function test_a_disputed_report_stays_public_with_the_warning_and_without_the_owners_words(): void
    {
        VehicleInspection::factory()
            ->disputed()
            ->create([
                'vehicle_id' => Vehicle::factory()->create()->id,
                'verification_code' => 'LDO-TEST-12',
            ]);

        $this->get('/laudo/LDO-TEST-12')
            ->assertOk()
            ->assertSee('O proprietário contesta este laudo')
            // Só o aviso é público; o texto que o dono escreveu, não (.ai/rules/public-lookup.md).
            ->assertDontSee('Não reconheço esta vistoria.');
    }

    public function test_an_expired_report_is_marked_and_keeps_answering(): void
    {
        VehicleInspection::factory()
            ->expired()
            ->create([
                'vehicle_id' => Vehicle::factory()->create()->id,
                'verification_code' => 'LDO-TEST-12',
            ]);

        $this->get('/laudo/LDO-TEST-12')
            ->assertOk()
            ->assertSee('Este laudo está vencido')
            ->assertSee('data-inspection-expired', false);
    }

    public function test_an_unknown_code_answers_the_same_page_as_the_seal(): void
    {
        $this->get('/laudo/LDO-NADA-00')
            ->assertNotFound()
            ->assertSee('Código não encontrado')
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_a_code_typed_loosely_lands_on_the_canonical_url(): void
    {
        $this->inspection();

        $this->get('/laudo/ldotest12')
            ->assertRedirect(route('inspections.public', 'LDO-TEST-12'));
    }

    public function test_the_single_public_field_sends_a_laudo_code_to_the_laudo(): void
    {
        $this->get('/verificar?codigo=ldo-test-12')
            ->assertRedirect(route('inspections.public', 'LDO-TEST-12'));
    }

    public function test_a_code_without_prefix_finds_the_laudo_when_no_seal_has_it(): void
    {
        $this->inspection();

        // Sem prefixo o código é ambíguo entre os dois documentos: quem existe desempata.
        $this->get('/verificar?codigo=test12')
            ->assertRedirect(route('inspections.public', 'LDO-TEST-12'));
    }

    public function test_a_code_without_prefix_prefers_the_seal(): void
    {
        $this->inspection();
        $this->sealedMaintenance();

        $this->get('/verificar?codigo=test12')
            ->assertRedirect(route('verification.show', 'RVL-TEST-12'));
    }

    public function test_a_bare_code_that_matches_nothing_falls_back_to_the_seal_page(): void
    {
        $this->get('/verificar?codigo=nada00')
            ->assertRedirect(route('verification.show', 'RVL-NADA-00'));
    }

    public function test_an_invalid_format_names_both_documents(): void
    {
        $this->get('/verificar?codigo=xx')
            ->assertStatus(422)
            ->assertSee('RVL-XXXX-XX')
            ->assertSee('LDO-XXXX-XX');
    }

    private function inspection(
        array $attributes = [],
        array $vehicleAttributes = [],
        ?Workshop $workshop = null,
        bool $withRestrictions = false,
    ): VehicleInspection {
        $workshop ??= Workshop::factory()->create(['name' => 'Vistoria Confiável']);

        $vehicle = Vehicle::factory()->create(array_merge([
            'brand' => 'Honda',
            'model' => 'Civic EX',
            'year' => 2022,
            'chassis' => '9BWZZZ377VT004251',
        ], $vehicleAttributes));

        $factory = VehicleInspection::factory();

        if ($withRestrictions) {
            $factory = $factory->withRestrictions();
        }

        return $factory->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'inspected_at' => '2026-03-18',
            'odometer_km' => 85280,
            'verification_code' => 'LDO-TEST-12',
        ], $attributes));
    }

    private function sealedMaintenance(): Maintenance
    {
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => Vehicle::factory()->create()->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $maintenance->forceFill([
            'verified_at' => now(),
            'verification_code' => 'RVL-TEST-12',
        ])->saveQuietly();

        return $maintenance;
    }

    /**
     * report_path não é fillable: só o serviço grava. No teste entra por forceFill, como no factory.
     */
    private function attachReport(VehicleInspection $inspection): void
    {
        $inspection->forceFill([
            'report_path' => 'inspections/'.$inspection->id.'/laudo.pdf',
            'report_original_name' => 'laudo.pdf',
        ])->saveQuietly();
    }
}
