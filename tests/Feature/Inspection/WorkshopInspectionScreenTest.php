<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use App\Enums\Portal;
use App\Features\VehicleInspections;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Tests\TestCase;

/**
 * As telas de vistoria cautelar da oficina: /oficina/vistorias.
 *
 * Três coisas se repetem aqui porque são as que a tela pode desfazer sem querer: a tela não
 * *oferece* o que a conta não pode fazer (menu, botão, formulário), o mascaramento do veículo é o
 * mesmo da Nova OS, e a leitura de hodômetro do laudo continua sem mexer no hodômetro do veículo.
 */
class WorkshopInspectionScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O único item de menu que depende da conta: Vistorias aparece só para quem pode emitir laudo
     * (App\Features\VehicleInspections, desligada por padrão). Menu que leva a um 403 é oferta
     * falsa — .ai/rules/features.md manda varrer também o que *sugere* a ação.
     *
     * É também o único portal com seis itens, e só nesse caso: o teto é a largura da topbar, e o
     * docblock de Portal::navigation() guarda a medida.
     */
    public function test_the_workshop_menu_only_offers_vistorias_when_the_account_can_issue_a_laudo(): void
    {
        [$user, $workshop] = $this->workshop();

        $this->assertSame(
            ['Início', 'OS', 'Validações', 'Modelos de garantia', 'Minha oficina'],
            array_column(Portal::Workshop->navigation($user->fresh()), 'label'),
        );

        // Ligar depois de uma leitura é escrita no escopo, não `define`: o Pennant com driver
        // database grava o valor resolvido na primeira consulta, e um define() posterior não o desfaz.
        Feature::for($workshop)->activate(VehicleInspections::class);

        $labels = array_column(Portal::Workshop->navigation($user->fresh()), 'label');

        $this->assertSame(
            ['Início', 'OS', 'Vistorias', 'Validações', 'Modelos de garantia', 'Minha oficina'],
            $labels,
        );
        $this->assertCount(6, $labels);
    }

    public function test_the_topbar_links_vistorias_only_with_the_feature_on(): void
    {
        [$user, $workshop] = $this->workshop();

        $this->actingAs($user->fresh())->get('/oficina/dashboard')
            ->assertOk()
            ->assertDontSee(route('workshop.inspections.index'));

        Feature::for($workshop)->activate(VehicleInspections::class);

        $this->actingAs($user->fresh())->get('/oficina/dashboard')
            ->assertOk()
            ->assertSee(route('workshop.inspections.index'));
    }

    /**
     * Sem a funcionalidade a tela explica, em vez de devolver 403 seco: a oficina está aprovada, o
     * que falta é a equipe ligar. E não oferece o formulário.
     */
    public function test_an_approved_workshop_without_the_feature_sees_the_explanation_instead_of_a_bare_403(): void
    {
        [$user] = $this->workshop();

        $this->actingAs($user->fresh())->get('/oficina/vistorias/nova')
            ->assertOk()
            ->assertSee('não está ativa nesta conta', false)
            ->assertDontSee('name="verdict"', false);
    }

    public function test_the_list_offers_the_button_only_with_the_feature_on(): void
    {
        [$user, $workshop] = $this->workshop();

        $this->actingAs($user->fresh())->get('/oficina/vistorias')
            ->assertOk()
            ->assertDontSee(route('workshop.inspections.create'));

        Feature::for($workshop)->activate(VehicleInspections::class);

        $this->actingAs($user->fresh())->get('/oficina/vistorias')
            ->assertOk()
            ->assertSee(route('workshop.inspections.create'));
    }

    public function test_a_forged_post_without_the_feature_is_refused(): void
    {
        [$user] = $this->workshop();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => 'ABC1D23']))
            ->assertForbidden();

        $this->assertDatabaseCount('vehicle_inspections', 0);
        $this->assertSame(0, $vehicle->inspections()->count());
    }

    public function test_the_plate_step_masks_the_vehicle_for_a_workshop_without_a_link_to_it(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();

        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'current_kilometers' => 123456,
        ]);

        $response = $this->actingAs($user->fresh())
            ->get('/oficina/vistorias/nova?license_plate=ABC1D23')
            ->assertOk()
            ->assertSee('data-vehicle-found', false);

        // Hodômetro do veículo soma registros que a consulta pública não lista: não sai para quem
        // só digitou uma placa (.ai/rules/public-lookup.md).
        $response->assertDontSee('123.456');
        $response->assertDontSee('data-vehicle-kilometers', false);
        $this->assertSame(123456, (int) $vehicle->fresh()->current_kilometers);
    }

    public function test_the_plate_step_shows_the_whole_vehicle_to_the_workshop_that_served_it(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();

        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'current_kilometers' => 123456,
        ]);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'kilometers' => 123456,
        ]);

        $this->actingAs($user->fresh())
            ->get('/oficina/vistorias/nova?license_plate=ABC1D23')
            ->assertOk()
            ->assertSee('data-vehicle-kilometers', false)
            ->assertSee('123.456');
    }

    public function test_the_verdict_is_asked_after_the_findings_not_before(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();

        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $html = $this->actingAs($user->fresh())
            ->get('/oficina/vistorias/nova?license_plate=ABC1D23')
            ->assertOk()
            ->getContent();

        $firstAxis = strpos($html, 'data-finding="');
        $lastAxis = strrpos($html, 'data-finding="');
        $odometer = strpos($html, 'name="odometer_km"');
        $verdict = strpos($html, 'name="verdict"');

        $this->assertIsInt($firstAxis);
        $this->assertIsInt($odometer);
        $this->assertIsInt($verdict);

        // O veredito é conclusão dos achados: perguntá-lo antes convida a marcar "Aprovado" e só
        // então olhar eixo por eixo, que é justamente o que assertCoherent recusa depois.
        $this->assertGreaterThan($lastAxis, $verdict, 'O veredito tem de vir depois dos oito eixos.');

        // Data e hodômetro são leitura, não juízo — e o eixo "Hodômetro" fala da leitura daqui.
        $this->assertLessThan($firstAxis, $odometer, 'O hodômetro lido tem de vir antes dos eixos.');
    }

    public function test_issuing_a_laudo_by_plate_creates_it_with_a_verification_code(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $response = $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'odometer_km' => '80000',
            'verdict' => InspectionVerdict::ApprovedWithRestrictions->value,
            'summary' => 'Repintura na porta dianteira direita.',
        ]));

        $inspection = VehicleInspection::query()->sole();

        $response->assertRedirect(route('workshop.inspections.show', $inspection));
        $this->assertSame($vehicle->id, $inspection->vehicle_id);
        $this->assertSame($workshop->id, $inspection->workshop_id);
        $this->assertSame($workshop->name, $inspection->workshop_name);
        $this->assertSame($user->id, $inspection->user_id);
        $this->assertSame(80000, (int) $inspection->odometer_km);
        $this->assertSame(InspectionVerdict::ApprovedWithRestrictions, $inspection->verdict);
        $this->assertStringStartsWith('LDO-', (string) $inspection->verification_code);
        $response->assertSessionHas('success', fn (string $message): bool => str_contains($message, (string) $inspection->verification_code));
    }

    /**
     * A leitura do laudo não alimenta vehicles.current_kilometers, e isso é permanente
     * (.ai/rules/inspections.md): o recálculo de VehicleMileageService é um max() a partir das OS e
     * apagaria em silêncio o que a vistoria gravasse ali.
     */
    public function test_issuing_a_laudo_never_moves_the_vehicle_odometer(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'current_kilometers' => 50000,
            'odometer_at_registration' => 50000,
        ]);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'odometer_km' => '90000',
        ]))->assertRedirect();

        $this->assertSame(50000, (int) $vehicle->fresh()->current_kilometers);
        $this->assertSame(90000, (int) VehicleInspection::query()->sole()->odometer_km);
    }

    /**
     * Veículo criado pelo chassi junto com o laudo nasce **sem** hodômetro: a única leitura que a
     * oficina tem é a do laudo, e ela não vira o hodômetro do veículo.
     */
    public function test_a_vehicle_created_by_chassis_is_born_without_an_odometer(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'chassis' => '9BWZZZ377VT004251',
            'odometer_km' => '90000',
            'new_vehicle' => ['brand' => 'Volkswagen', 'model' => 'Gol', 'year' => '2015'],
        ]))->assertRedirect();

        $vehicle = Vehicle::query()->sole();

        $this->assertSame('9BWZZZ377VT004251', $vehicle->chassis);
        $this->assertNull($vehicle->odometer_at_registration);
        $this->assertSame(0, (int) $vehicle->current_kilometers);
        $this->assertSame(90000, (int) VehicleInspection::query()->sole()->odometer_km);
    }

    public function test_a_chassis_that_already_has_an_owner_is_refused_with_the_plate_hint(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['chassis' => '9BWZZZ377VT004251']);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload(['chassis' => '9BWZZZ377VT004251']))
            ->assertSessionHasErrors('chassis');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    /**
     * A garantia do serviço chega à tela como erro do campo, não como 500: "Aprovado" não convive
     * com eixo reprovado.
     */
    public function test_approved_over_a_failed_axis_comes_back_as_a_field_error(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'verdict' => InspectionVerdict::Approved->value,
            'findings' => [
                InspectionFinding::Structural->value => ['status' => InspectionFindingStatus::Fail->value, 'note' => 'Longarina amassada.'],
            ],
        ]))->assertSessionHasErrors('verdict');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    public function test_the_odometer_axis_cannot_say_nothing_is_wrong_against_the_history(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();

        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'kilometers' => 90000,
        ]);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'odometer_km' => '40000',
            'findings' => [
                InspectionFinding::Odometer->value => ['status' => InspectionFindingStatus::Ok->value],
            ],
        ]))->assertSessionHasErrors('findings.odometer.status');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    /**
     * O mesmo caso, com o eixo relatando o achado: a leitura abaixo do histórico é aceita, porque é
     * justamente o indício de adulteração que a cautelar existe para encontrar.
     */
    public function test_a_reading_below_the_history_is_accepted_when_the_axis_reports_it(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();

        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'kilometers' => 90000,
        ]);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'odometer_km' => '40000',
            'verdict' => InspectionVerdict::Rejected->value,
            'findings' => [
                InspectionFinding::Odometer->value => ['status' => InspectionFindingStatus::Fail->value, 'note' => 'Leitura abaixo de registro anterior.'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(40000, (int) VehicleInspection::query()->sole()->odometer_km);
    }

    public function test_a_future_inspection_date_is_refused_by_the_form_request(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'inspected_at' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('inspected_at');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    public function test_the_pdf_is_stored_under_a_random_name_and_only_pdf_is_accepted(): void
    {
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo do cliente Maria 123.456.789-00.pdf', 120, 'application/pdf'),
        ]))->assertRedirect();

        $inspection = VehicleInspection::query()->sole();

        $this->assertStringStartsWith('inspections/', (string) $inspection->report_path);
        $this->assertStringNotContainsString('Maria', (string) $inspection->report_path);
        $this->assertStringNotContainsString('123.456.789', (string) $inspection->report_path);
        $this->assertSame('laudo do cliente Maria 123.456.789-00.pdf', $inspection->report_original_name);
        AppStorage::disk()->assertExists($inspection->report_path);
    }

    public function test_a_report_that_is_not_a_pdf_is_refused(): void
    {
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]))->assertSessionHasErrors('report');

        $this->assertDatabaseCount('vehicle_inspections', 0);
    }

    public function test_the_list_shows_only_the_laudos_of_the_signing_workshop(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();
        [, $other] = $this->workshop();

        $mine = VehicleInspection::factory()->create(['workshop_id' => $workshop->id]);
        $theirs = VehicleInspection::factory()->create(['workshop_id' => $other->id]);

        $this->actingAs($user->fresh())->get('/oficina/vistorias')
            ->assertOk()
            ->assertSee($mine->verification_code)
            ->assertDontSee($theirs->verification_code);
    }

    public function test_another_workshop_cannot_open_correct_or_delete_a_laudo(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [, $workshop] = $this->workshop();
        [$otherUser] = $this->workshop();

        $inspection = VehicleInspection::factory()->create(['workshop_id' => $workshop->id]);

        $this->actingAs($otherUser->fresh())->get('/oficina/vistorias/'.$inspection->id)->assertForbidden();
        $this->actingAs($otherUser->fresh())->get('/oficina/vistorias/'.$inspection->id.'/editar')->assertForbidden();
        $this->actingAs($otherUser->fresh())->put('/oficina/vistorias/'.$inspection->id, $this->payload())->assertForbidden();
        $this->actingAs($otherUser->fresh())->delete('/oficina/vistorias/'.$inspection->id)->assertForbidden();
    }

    /**
     * Corrigir um campo que não é veredito, achado nem hodômetro não faz o serviço reavaliar as
     * garantias: laudo gravado antes da regra não tranca a própria tela de edição.
     */
    public function test_the_issuer_corrects_the_date_of_a_laudo_that_would_not_pass_the_guard_today(): void
    {
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();

        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $inspection = VehicleInspection::factory()
            ->failing(InspectionFinding::Structural)
            ->create([
                'vehicle_id' => $vehicle->id,
                'workshop_id' => $workshop->id,
                'inspected_at' => now()->subMonth()->toDateString(),
            ]);
        // Estado que a garantia de hoje recusaria: "Aprovado" com um eixo reprovado.
        $inspection->forceFill(['verdict' => InspectionVerdict::Approved])->save();

        // O formulário reenvia os oito eixos como estão, então a afirmação não mudou: só a data.
        $this->actingAs($user->fresh())->put('/oficina/vistorias/'.$inspection->id, [
            'inspected_at' => now()->subWeek()->toDateString(),
            'verdict' => InspectionVerdict::Approved->value,
            'findings' => $inspection->fresh()->findings,
        ])->assertSessionHasNoErrors()->assertRedirect(route('workshop.inspections.show', $inspection));

        $this->assertSame(
            now()->subWeek()->toDateString(),
            $inspection->fresh()->inspected_at->toDateString(),
        );
    }

    public function test_deleting_a_laudo_removes_the_pdf_from_the_storage(): void
    {
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$user, $workshop] = $this->workshop();
        Vehicle::factory()->create(['license_plate' => 'ABC1D23']);

        $this->actingAs($user->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf'),
        ]))->assertRedirect();

        $inspection = VehicleInspection::query()->sole();
        $path = (string) $inspection->report_path;
        AppStorage::disk()->assertExists($path);

        $this->actingAs($user->fresh())->delete('/oficina/vistorias/'.$inspection->id)
            ->assertRedirect(route('workshop.inspections.index'));

        $this->assertDatabaseCount('vehicle_inspections', 0);
        AppStorage::disk()->assertMissing($path);
        $this->assertSame($workshop->id, $workshop->fresh()->id);
    }

    /**
     * O PDF nunca sai pelo caminho do storage: quem abre é a policy, e ela responde a mesma coisa
     * que a redação (showsFullReportTo). Uma rota de arquivo mais frouxa que a tela devolveria por
     * download o que a tela esconde.
     */
    public function test_the_report_route_answers_the_same_as_the_redaction(): void
    {
        Storage::fake('public');
        $this->enableVehicleInspectionsForEveryone();
        [$issuerUser, $workshop] = $this->workshop();
        [$otherUser] = $this->workshop();

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser($owner, $vehicle);

        $this->actingAs($issuerUser->fresh())->post('/oficina/vistorias', $this->payload([
            'license_plate' => 'ABC1D23',
            'report' => UploadedFile::fake()->create('laudo.pdf', 100, 'application/pdf'),
        ]))->assertRedirect();

        $inspection = VehicleInspection::query()->sole();
        $url = route('inspections.report', $inspection);

        $this->actingAs($issuerUser->fresh())->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($owner->fresh())->get($url)->assertOk();
        $this->actingAs($otherUser->fresh())->get($url)->assertForbidden();
        $this->post('/logout');
        $this->get($url)->assertRedirect();
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

    /**
     * UserFactory::configure() já cria a oficina (aprovada) da conta de oficina.
     *
     * @return array{0: User, 1: Workshop}
     */
    private function workshop(): array
    {
        $user = User::factory()->asWorkshop()->create();

        return [$user->fresh(), $user->fresh()->workshop];
    }
}
