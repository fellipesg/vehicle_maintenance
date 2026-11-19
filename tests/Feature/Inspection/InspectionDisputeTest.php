<?php

namespace Tests\Feature\Inspection;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Notifications\VehicleInspectionDisputedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A contestação do proprietário sobre um laudo do carro dele.
 *
 * Até aqui o laudo era a única afirmação do produto que ninguém podia responder: a oficina emitia,
 * as telas já desenhavam "Contestado pelo proprietário" e a coluna nunca era escrita fora de teste.
 *
 * O que o teste guarda é a forma da resposta: contestar **marca e não esconde** (o laudo continua na
 * ficha e na conferência pública, com a contestação à vista), é do dono atual e não pede CRLV-e, e
 * **não mexe no hodômetro do veículo** — o irmão da OS recalcula, e copiar isso aqui seria a porta
 * dos fundos da regra de que a leitura da vistoria não alimenta vehicles.current_kilometers.
 */
class InspectionDisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_disputes_the_report_of_their_own_car(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($owner)
            ->post(route('user.inspections.dispute', $inspection), ['reason' => 'O chassi do laudo não é o do meu carro.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $inspection->refresh();

        $this->assertNotNull($inspection->owner_disputed_at);
        $this->assertSame($owner->id, $inspection->owner_disputed_by_user_id);
        $this->assertSame('O chassi do laudo não é o do meu carro.', $inspection->owner_dispute_reason);
    }

    /**
     * O ponto inteiro da contestação: ela marca, não suprime. Quem está comprando vê as duas
     * versões e decide — a alavanca de sumir com o laudo não é do proprietário.
     */
    public function test_a_disputed_report_stays_on_every_surface(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Nunca levei o carro nessa vistoria.']);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-inspection-card', false)
            ->assertSee('Contestado pelo proprietário');

        // E a conferência pública continua respondendo, com a marca e sem o texto do dono.
        $this->get(route('inspections.public', $inspection->verification_code))
            ->assertOk()
            ->assertSee('Aprovado')
            ->assertDontSee('Nunca levei o carro nessa vistoria.');
    }

    /**
     * O irmão desta ação (MaintenanceOwnerDecisionService::dispute) recalcula o hodômetro, porque a
     * OS contestada sai da conta. Aqui não há conta de onde sair: a leitura da vistoria nunca
     * alimentou vehicles.current_kilometers, e chamar o VehicleMileageService por simetria seria a
     * porta dos fundos dessa regra (.ai/rules/inspections.md).
     */
    public function test_disputing_never_touches_the_vehicle_odometer(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $vehicle->forceFill(['current_kilometers' => 90_000])->save();
        $inspection = $this->inspection($vehicle, ['odometer_km' => 42_000]);

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), []);

        $this->assertSame(90_000, $vehicle->fresh()->current_kilometers);
    }

    public function test_the_issuing_workshop_is_told_because_it_is_who_can_fix_it(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = Workshop::factory()->create(['name' => 'Vistoria Confiável', 'user_id' => $workshopUser->id]);
        $inspection = $this->inspection($vehicle, ['workshop_id' => $workshop->id]);

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Chassi divergente.']);

        Notification::assertSentTo(
            $workshopUser,
            VehicleInspectionDisputedNotification::class,
            function (VehicleInspectionDisputedNotification $notification) use ($inspection, $workshopUser): bool {
                $payload = $notification->toArray($workshopUser);
                $mail = $notification->toMail($workshopUser);

                return $payload['type'] === VehicleInspectionDisputedNotification::TYPE
                    && $payload['inspection_id'] === $inspection->id
                    && $payload['reason'] === 'Chassi divergente.'
                    // O texto não pode prometer que o laudo sai de circulação: ele não sai.
                    && collect($mail->introLines)->contains(fn (string $line): bool => str_contains($line, 'continua no histórico'));
            }
        );
    }

    public function test_disputing_again_keeps_the_first_date_and_takes_the_newest_reason(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        Carbon::setTestNow('2026-03-10 09:00:00');
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Primeira versão.']);
        $firstDisputedAt = $inspection->fresh()->owner_disputed_at;

        Carbon::setTestNow('2026-04-01 09:00:00');
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Agora com o documento em mãos.']);

        $inspection->refresh();
        Carbon::setTestNow();

        $this->assertTrue($firstDisputedAt->equalTo($inspection->owner_disputed_at));
        $this->assertSame('Agora com o documento em mãos.', $inspection->owner_dispute_reason);
    }

    public function test_the_owner_withdraws_the_dispute(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->disputed()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('user.inspections.dispute.withdraw', $inspection))
            ->assertRedirect()
            ->assertSessionHas('success');

        $inspection->refresh();

        $this->assertNull($inspection->owner_disputed_at);
        $this->assertNull($inspection->owner_disputed_by_user_id);
        $this->assertNull($inspection->owner_dispute_reason);
    }

    public function test_someone_who_is_not_the_current_owner_cannot_dispute(): void
    {
        Notification::fake();

        [, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)
            ->post(route('user.inspections.dispute', $inspection), ['reason' => 'Não é meu carro, mas quero marcar.'])
            ->assertRedirect()
            ->assertSessionHasErrors('dispute');

        $this->assertNull($inspection->fresh()->owner_disputed_at);
    }

    /**
     * Consignação não transfere a resposta: a loja segura o carro, o dono é quem contesta o que
     * afirmam sobre ele.
     */
    public function test_a_consignment_dealer_cannot_dispute_for_the_owner(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

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
            'history_access_status' => 'approved',
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);

        // A rota é do portal do proprietário: a conta de lojista nem entra nela — o middleware de
        // área (user.type:user) a devolve para a própria, sem chegar ao serviço.
        $this->actingAs($dealer->fresh())
            ->post(route('user.inspections.dispute', $inspection), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($inspection->fresh()->owner_disputed_at);

        // E na ficha dele o laudo aparece com a marca, sem a ação.
        $this->actingAs($dealer->fresh())
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-inspection-card', false)
            ->assertDontSee('Não concordo com este laudo');
    }

    public function test_a_suppressed_report_cannot_be_disputed(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->hiddenByStaff()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $this->actingAs($owner)
            ->post(route('user.inspections.dispute', $inspection), [])
            ->assertRedirect()
            ->assertSessionHasErrors('dispute');

        $this->assertNull($inspection->fresh()->owner_disputed_at);
    }

    /**
     * A ação é do dono atual; a marca é de todo mundo. Sem a posse confirmada pelo CRLV-e também
     * vale: é a alavanca que ele tem sem o documento.
     */
    public function test_the_owner_sees_the_action_on_the_vehicle_page_without_the_crlv(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->assertNull($owner->vehicles()->first()?->pivot?->ownership_verified_at);

        $this->actingAs($owner->fresh())
            ->get(route('user.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Não concordo com este laudo')
            ->assertSee(route('user.inspections.dispute', $inspection), false);
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
     * @param  array<string, mixed>  $attributes
     */
    private function inspection(Vehicle $vehicle, array $attributes = []): VehicleInspection
    {
        return VehicleInspection::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
            'summary' => null,
        ], $attributes));
    }
}
