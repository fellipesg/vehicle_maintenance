<?php

namespace Tests\Feature\Inspection;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O laudo na ficha do veículo (<x-vehicle.detail>), a mesma dos quatro portais e da busca.
 *
 * Até aqui a vistoria existia e ninguém a via: a oficina emitia, o laudo respondia em
 * /laudo/{código} para quem tinha o código na mão, e o proprietário não encontrava em tela nenhuma
 * o laudo do próprio carro. A aba Vistorias é essa metade do histórico.
 *
 * Duas coisas que o teste guarda, além de "aparece": o laudo **não** entra nas contas de manutenção
 * (.ai/rules/inspections.md: carro com três laudos e zero serviços não lê como "3 serviços"), e a
 * aba segue a mesma régua de quem vê o quê — o que a listagem mostra é o que
 * App\Support\Inspection\InspectionRedactor deixa passar, nem uma linha mais.
 */
class VehicleDetailInspectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_finds_the_report_of_their_own_car(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = $this->inspection($vehicle, [
            'summary' => 'Carro em bom estado geral, com repintura na porta dianteira direita.',
            'odometer_km' => 85280,
            'inspected_at' => '2026-03-18',
            'valid_until' => '2027-03-18',
        ], withRestrictions: true);
        $this->attachReport($inspection);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Vistorias')
            ->assertSee('Vistoria cautelar')
            ->assertSee('Vistoria Confiável')
            ->assertSee('Aprovado com restrições')
            ->assertSee('85.280 km lidos')
            ->assertSee('18/03/2026')
            ->assertSee('18/03/2027')
            ->assertSee($inspection->verification_code)
            // O eixo com apontamento sai pelo nome; o card não repete os oito com "Não avaliado".
            ->assertSee('Pintura e indícios de reparo')
            ->assertDontSee('Recall pendente')
            // Dono tem o laudo inteiro: a prosa e o PDF saem (VehicleInspection::showsFullReportTo).
            ->assertSee('Carro em bom estado geral')
            ->assertSee(route('inspections.report', $inspection), false)
            // O card leva à mesma conferência que o comprador abre.
            ->assertSee(route('inspections.public', $inspection->verification_code), false);
    }

    public function test_a_vehicle_without_a_report_has_no_inspections_tab(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        // Aba vazia em quase todo veículo ensinaria a ignorar as abas, e não há ação a oferecer:
        // vistoria é algo que o comprador encomenda, não algo que se registra por esta tela.
        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('data-inspection-list', false)
            ->assertDontSee('Vistorias cautelares');
    }

    public function test_the_report_stays_out_of_the_maintenance_counts(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);
        $this->inspection($vehicle);
        $this->inspection($vehicle);

        $html = $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('1 manutenção')
            ->getContent();

        // Duas vistorias e uma manutenção: a ficha não pode dizer "3" em lugar nenhum da
        // procedência, e a faixa de procedência continua com um ponto por manutenção.
        $this->assertStringNotContainsString('3 manutenções', $html);
        $this->assertSame(1, preg_match_all('/data-maintenance-card/', $html));
        $this->assertSame(2, preg_match_all('/data-inspection-card=/', $html));
    }

    public function test_the_report_card_does_not_borrow_the_workshop_seal_visual(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        $this->inspection($vehicle);

        $html = $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->getContent();

        // O anel teal e o trilho afirmam "eu fiz este serviço"; o laudo afirma "eu examinei e
        // atesto o que encontrei". A asserção é dentro do card, e não na página: a legenda de
        // procedência da linha do tempo usa .prov-marker de direito (.ai/rules/theme.md).
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $card = $document->querySelector('[data-inspection-card]');

        $this->assertNotNull($card, 'O card do laudo não foi desenhado.');

        $cardHtml = $document->saveHtml($card);

        foreach (['prov-card', 'prov-seal', 'prov-marker', 'prov-rail', 'prov-verified', 'prov-declared'] as $provenanceClass) {
            $this->assertStringNotContainsString($provenanceClass, $cardHtml);
        }
    }

    public function test_a_report_the_staff_suppressed_is_gone_from_the_owners_page(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        VehicleInspection::factory()
            ->hiddenByStaff()
            ->create(['vehicle_id' => $vehicle->id, 'workshop_id' => Workshop::factory()->create()->id]);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('data-inspection-card', false);
    }

    /**
     * A marca é de todo mundo; o texto da contestação é só de quem o escreveu — ele precisa relê-lo
     * para decidir se retira. Para os outros portais o dado é "o dono discorda", não a prosa.
     */
    public function test_a_disputed_report_is_marked_and_only_the_owner_reads_their_own_words(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        VehicleInspection::factory()
            ->disputed()
            ->create(['vehicle_id' => $vehicle->id, 'workshop_id' => Workshop::factory()->create()->id]);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Contestado pelo proprietário')
            ->assertSee('Você contestou este laudo')
            ->assertSee('Não reconheço esta vistoria.');

        $this->actingAs(User::factory()->asUser()->asAdmin()->create())
            ->get(route('admin.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Contestado pelo proprietário')
            ->assertDontSee('Você contestou este laudo')
            ->assertDontSee('Não reconheço esta vistoria.');
    }

    public function test_an_expired_report_is_marked_and_keeps_showing(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        VehicleInspection::factory()
            ->expired()
            ->create(['vehicle_id' => $vehicle->id, 'workshop_id' => Workshop::factory()->create()->id]);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-inspection-card', false)
            ->assertSee('data-inspection-expired', false)
            ->assertSee('Vencido');
    }

    public function test_the_search_shows_a_stranger_the_minimal_form(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'LDO4B81']);
        $this->attachVehicleToUser($owner, $vehicle);

        $inspection = $this->inspection($vehicle, [
            'summary' => 'Carro em bom estado geral, com repintura na porta dianteira direita.',
        ], withRestrictions: true);
        $this->attachReport($inspection);

        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger->fresh())
            ->get(route('vehicle.search', ['identifier' => 'LDO4B81']))
            ->assertOk()
            // O veredito e os eixos com apontamento são públicos: é o que serve a quem está comprando.
            ->assertSee('Aprovado com restrições')
            ->assertSee('Pintura e indícios de reparo')
            // A prosa e o PDF, não (App\Support\Inspection\InspectionRedactor).
            ->assertDontSee('Carro em bom estado geral')
            ->assertDontSee(route('inspections.report', $inspection), false);
    }

    public function test_a_rejected_report_names_the_failing_axis_to_a_stranger(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'LDO9R33']);
        $this->attachVehicleToUser($owner, $vehicle);

        VehicleInspection::factory()
            ->rejected()
            ->create(['vehicle_id' => $vehicle->id, 'workshop_id' => Workshop::factory()->create()->id]);

        $this->actingAs(User::factory()->asUser()->create()->fresh())
            ->get(route('vehicle.search', ['identifier' => 'LDO9R33']))
            ->assertOk()
            ->assertSee('Reprovado')
            ->assertSee('Chassi e numeração do motor');
    }

    public function test_the_admin_page_shows_the_report(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->inspection($vehicle);

        $this->actingAs(User::factory()->create(['is_admin' => true])->fresh())
            ->get(route('admin.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Vistorias cautelares')
            ->assertSee('data-inspection-card', false);
    }

    /**
     * Consignação sem liberação do histórico: a loja vê só o que ela própria registrou. O laudo de
     * terceiro fica fora junto com as manutenções — mostrar uma metade e esconder a outra deixaria
     * a ficha afirmando o histórico que o portão protege (Vehicle::restrictHistoryTo).
     */
    public function test_a_consignment_without_history_release_sees_no_third_party_report(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'CON5I1G']);
        $owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($owner, $vehicle);

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

        $this->inspection($vehicle, ['tenant_id' => $owner->tenant_id]);

        $this->actingAs($dealer->fresh())
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('data-inspection-card', false);
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
