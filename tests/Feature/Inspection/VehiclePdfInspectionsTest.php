<?php

namespace Tests\Feature\Inspection;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\DemoWarrantyPdfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A vistoria cautelar no PDF do histórico — o arquivo que o proprietário manda para quem está
 * comprando, e que circula fora do RevisaLog.
 *
 * O PDF é o lugar onde o laudo vale mais: é ele que chega a quem não tem conta, não abre
 * /laudo/{código} e está decidindo a compra. Três coisas o teste guarda:
 *
 * 1. o laudo **não** entra em nenhuma conta de manutenção da capa (contador de procedência e linha
 *    de pontos), pela mesma razão de ser tabela própria (.ai/rules/inspections.md);
 * 2. o PDF segue a régua de quem pediu: a consulta limitada (Vehicle::inspectionsVisibleTo) tira o
 *    laudo de um carro cujo histórico o leitor não tem, e o InspectionRedactor tira o suprimido pela
 *    equipe e a prosa de quem só vê a forma mínima;
 * 3. a página do laudo não empresta o contrato visual de procedência — nem as classes `.prov-*`, nem
 *    o teal #0f766e, nem o âmbar #92400e (.ai/rules/theme.md).
 */
class VehiclePdfInspectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_the_owners_pdf_carries_the_verdict_the_findings_and_the_code(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $inspection = $this->inspection($vehicle, [
            'summary' => 'Repintura na porta dianteira direita, sem indício de avaria estrutural.',
            'odometer_km' => 85280,
            'inspected_at' => '2026-03-18',
            'valid_until' => '2027-03-18',
        ], withRestrictions: true);

        $text = $this->pdfText($vehicle, $owner);

        $this->assertStringContainsString('Vistoria cautelar', $text);
        // O selo do veredito sai em caixa alta (text-transform), como o kicker do Selo da oficina.
        $this->assertStringContainsStringIgnoringCase('Veredito da vistoria', $text);
        $this->assertStringContainsString('Aprovado com restrições', $text);
        $this->assertStringContainsString('Vistoria Confiável', $text);
        $this->assertStringContainsString('18/03/2026', $text);
        $this->assertStringContainsString('18/03/2027', $text);
        $this->assertStringContainsString('85.280 km', $text);
        $this->assertStringContainsString((string) $inspection->verification_code, $text);
        $this->assertStringContainsString('revisalog.com.br/laudo/'.$inspection->verification_code, $text);

        // Os oito eixos saem, um por um: aqui é o documento, e "Não avaliado" é informação.
        $this->assertStringContainsString('Achados da vistoria', $text);
        $this->assertStringContainsString('Pintura e indícios de reparo', $text);
        $this->assertStringContainsString('Ponto de atenção', $text);
        $this->assertStringContainsString('Recall pendente', $text);
        $this->assertStringContainsString('Não avaliado', $text);

        // Dono tem o laudo inteiro: a prosa do vistoriador sai (VehicleInspection::showsFullReportTo).
        $this->assertStringContainsString('Repintura na porta dianteira direita', $text);
        $this->assertStringContainsString('Resumo do vistoriador', $text);
    }

    /**
     * A capa conta manutenção; o laudo fica de fora. Carro com um serviço e dois laudos não pode
     * ler como três registros de manutenção.
     */
    public function test_the_reports_stay_out_of_the_maintenance_counts_on_the_cover(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $this->inspection($vehicle, ['inspected_at' => '2026-03-18']);
        $this->inspection($vehicle, ['inspected_at' => '2025-09-02'], withRestrictions: true);

        $export = $this->export($vehicle, $owner);
        $html = $export['html'];

        $this->assertStringContainsString('<span class="prov-counter--sealed">1 com selo</span>', $html);
        $this->assertStringContainsString('<span class="prov-counter--declared">0 declaradas</span>', $html);
        $this->assertSame(1, preg_match_all('/class="prov-dot prov-dot--(verified|declared)"/', $html));

        // E os dois laudos aparecem, na sua própria conta.
        $this->assertStringContainsString('2 laudos registrados', $html);
        $this->assertSame(2, preg_match_all('/class="document-table inspection-document"/', $html));

        // Capa + a OS + um laudo por página.
        $this->assertSame(4, DemoWarrantyPdfValidator::countPages($export['pdf']));
    }

    public function test_the_report_page_does_not_borrow_the_provenance_visual(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $this->inspection($vehicle, [], withRestrictions: true);

        $document = \Dom\HTMLDocument::createFromString($this->pdfHtml($vehicle, $owner), LIBXML_NOERROR);
        $page = $document->querySelector('.inspection-document');

        $this->assertNotNull($page, 'O PDF não desenhou a página do laudo.');

        $pageHtml = $document->saveHtml($page);

        foreach (['prov-', 'seal--verified', 'seal--declared', '#0f766e', '#92400e', 'Selo da oficina'] as $provenanceTerm) {
            $this->assertStringNotContainsString($provenanceTerm, $pageHtml);
        }

        // O veredito é quem carrega o significado, nas cores semânticas do badge da web.
        $this->assertStringContainsString('verdict--warning', $pageHtml);
    }

    public function test_a_report_the_staff_suppressed_is_not_in_the_pdf(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $hidden = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Suprimida'])->id,
            'workshop_name' => 'Vistoria Suprimida',
            'summary' => 'Laudo com chassi divergente.',
        ]);

        $text = $this->pdfText($vehicle, $owner);

        $this->assertStringNotContainsString('Vistoria Suprimida', $text);
        $this->assertStringNotContainsString((string) $hidden->verification_code, $text);
        $this->assertStringNotContainsString('Vistoria cautelar', $text);
    }

    /**
     * Consignação sem liberação do histórico: o laudo de terceiro fica fora do PDF, como as
     * manutenções do dono. O PDF é o histórico inteiro, e o portão vale para as duas metades.
     */
    public function test_a_consignment_without_history_release_gets_no_third_party_report(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle, ['tenant_id' => $owner->tenant_id]);

        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => false,
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $dealer->id,
            'tenant_id' => $dealer->tenant_id,
            'owner_user_id' => $owner->id,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
        ]);

        $text = $this->pdfText($vehicle, $dealer->fresh());

        $this->assertStringNotContainsString('Vistoria cautelar', $text);
        $this->assertStringNotContainsString((string) $inspection->verification_code, $text);
    }

    /**
     * Sem quem pediu (o comando de demonstração), vale a forma mínima: veredito, data, hodômetro,
     * validade, emissor, código e o status de cada eixo — e nenhuma linha de texto livre.
     */
    public function test_a_pdf_without_a_viewer_keeps_the_verdict_and_drops_the_prose(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle, [
            'summary' => 'Prosa que descreve o carro de alguém.',
        ], withRestrictions: true);

        $text = $this->pdfText($vehicle, null);

        $this->assertStringContainsString('Aprovado com restrições', $text);
        $this->assertStringContainsString('Ponto de atenção', $text);
        $this->assertStringContainsString((string) $inspection->verification_code, $text);
        $this->assertStringNotContainsString('Prosa que descreve', $text);
        $this->assertStringNotContainsString('Resumo do vistoriador', $text);
        // A observação do eixo também é texto livre: sem nenhuma, a coluna não é desenhada.
        $this->assertStringNotContainsString('Repintura na porta', $text);
        $this->assertStringNotContainsString('Observação', $text);
    }

    public function test_an_expired_report_is_marked_and_keeps_showing(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $this->inspection($vehicle, [
            'inspected_at' => now()->subMonths(8)->format('Y-m-d'),
            'valid_until' => now()->subMonths(5)->format('Y-m-d'),
        ]);

        $text = $this->pdfText($vehicle, $owner);

        $this->assertStringContainsString('Vistoria cautelar', $text);
        $this->assertStringContainsString('Validade terminada em', $text);
        $this->assertStringContainsString('rodou desde então', $text);
    }

    public function test_a_disputed_report_is_marked_without_the_owners_words(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        VehicleInspection::factory()->disputed()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
            'summary' => null,
        ]);

        $text = $this->pdfText($vehicle, $owner);

        $this->assertStringContainsString('Contestado pelo proprietário', $text);
        $this->assertStringNotContainsString('Não reconheço esta vistoria', $text);
    }

    /**
     * Veículo só com laudo: a capa quebra a página e o laudo tem a sua, sem folha em branco entre as
     * duas (a armadilha de page-break que .ai/rules/pdfs.md descreve para as OS).
     */
    public function test_a_vehicle_with_a_report_and_no_maintenance_has_no_blank_page(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $this->inspection($vehicle);

        $export = $this->export($vehicle, $owner);

        $this->assertSame(2, DemoWarrantyPdfValidator::countPages($export['pdf']));
        $this->assertStringContainsString('Nenhuma manutenção registrada', $export['text']);
        $this->assertStringContainsString('Vistorias cautelares', $export['text']);

        // E o laudo começa na página dele: a capa quebra depois de si mesma mesmo sem manutenção.
        $firstPage = Str::before($export['text'], 'Página 1 de');
        $this->assertStringContainsString('Vistorias cautelares', $firstPage);
        $this->assertStringNotContainsStringIgnoringCase('Veredito da vistoria', $firstPage);
    }

    public function test_a_vehicle_without_reports_keeps_the_pdf_as_it_was(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $html = $this->pdfHtml($vehicle, $owner);

        $this->assertStringNotContainsString('Vistorias cautelares', $html);
        $this->assertStringNotContainsString('class="document-table inspection-document"', $html);
        $this->assertStringNotContainsString('revisalog.com.br/laudo/', $html);
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Civic',
            'license_plate' => 'ABC1D23',
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->fresh(), $vehicle->fresh()];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function inspection(Vehicle $vehicle, array $attributes = [], bool $withRestrictions = false): VehicleInspection
    {
        $factory = VehicleInspection::factory();

        if ($withRestrictions) {
            $factory = $factory->withRestrictions();
        }

        return $factory->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
            'summary' => null,
        ], $attributes));
    }

    /**
     * Gera o PDF de verdade, pelo exporter, e devolve as três leituras dele.
     *
     * O `html` é o que o exporter entregou ao Dompdf: os dados da view são capturados por um
     * View::composer e re-renderizados, então as classes e as cores conferidas abaixo são as que
     * o laudo recebeu do exporter — e não um palpite do teste sobre o que ele monta. O texto do PDF
     * não mostra nada disso.
     *
     * @return array{pdf: string, text: string, html: string}
     */
    private function export(Vehicle $vehicle, ?User $viewer): array
    {
        $viewData = null;
        View::composer('pdfs.vehicle_maintenance_export', function ($view) use (&$viewData): void {
            $viewData = $view->getData();
        });

        $exporter = app(VehicleMaintenancePdfExporter::class);
        $file = $exporter->generate($vehicle->fresh(), viewer: $viewer);

        try {
            $this->assertIsArray($viewData, 'A view do PDF não foi renderizada.');

            return [
                'pdf' => $file['content'],
                'text' => DemoWarrantyPdfValidator::extractText($file['content']),
                'html' => view('pdfs.vehicle_maintenance_export', $viewData)->render(),
            ];
        } finally {
            $exporter->cleanupTemps($file['temps']);
        }
    }

    private function pdfText(Vehicle $vehicle, ?User $viewer): string
    {
        return $this->export($vehicle, $viewer)['text'];
    }

    private function pdfHtml(Vehicle $vehicle, ?User $viewer): string
    {
        return $this->export($vehicle, $viewer)['html'];
    }
}
