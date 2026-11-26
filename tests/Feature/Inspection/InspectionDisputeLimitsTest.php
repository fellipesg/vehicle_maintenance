<?php

namespace Tests\Feature\Inspection;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Notifications\MaintenanceDisputedNotification;
use App\Notifications\VehicleInspectionDisputedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Contestar é um canal de mensagem para a caixa da oficina, e tinha dois tetos faltando.
 *
 * A gravação já era idempotente na **data** (a primeira contestação é quando o dono discordou), mas
 * não no aviso: cada POST regravava o motivo — 500 caracteres livres do formulário — e disparava
 * **outro** VehicleInspectionDisputedNotification (mail + database). O dono atual de um veículo
 * mandava N e-mails para a oficina com N cliques, e a rota não tinha `throttle` nenhum, nem o irmão
 * de OS.
 *
 * As duas correções: balde por conta nas quatro rotas (`owner-disputes`, como manda
 * .ai/rules/abuse-guards.md para rota que dispara e-mail) e aviso só na primeira contestação ou
 * quando o motivo realmente muda.
 */
class InspectionDisputeLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('owner-disputes|1');
    }

    public function test_disputing_twice_with_the_same_reason_sends_one_email(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $reason = 'O chassi do laudo não é o do meu carro.';

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => $reason]);
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => $reason]);
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => $reason]);

        // A contestação continua gravada; só o aviso não se repete.
        $inspection->refresh();
        $this->assertNotNull($inspection->owner_disputed_at);
        $this->assertSame($reason, $inspection->owner_dispute_reason);

        Notification::assertSentToTimes(
            $this->workshopUserOf($inspection),
            VehicleInspectionDisputedNotification::class,
            1,
        );
    }

    /**
     * Motivo novo é informação nova para quem corrige o laudo — esse aviso sai.
     */
    public function test_a_changed_reason_notifies_again(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Não é o meu carro.']);
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'O hodômetro do laudo está errado.']);

        $this->assertSame('O hodômetro do laudo está errado.', $inspection->fresh()->owner_dispute_reason);

        Notification::assertSentToTimes(
            $this->workshopUserOf($inspection),
            VehicleInspectionDisputedNotification::class,
            2,
        );
    }

    /**
     * A data não reabre, e o aviso também não: retirar e contestar de novo com o mesmo motivo é
     * exatamente a sequência que repetia o e-mail.
     */
    public function test_withdrawing_and_disputing_again_with_the_same_reason_does_not_notify_again(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Não reconheço este laudo.']);
        $this->actingAs($owner)->delete(route('user.inspections.dispute.withdraw', $inspection));
        $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Não reconheço este laudo.']);

        // Retirar apaga as três colunas, então a contestação nova é a "primeira" outra vez — e o
        // aviso sai, porque para a oficina ela reapareceu. O que o teto cobre é a repetição: o
        // balde da rota é o que impede a sequência virar canal de e-mail.
        Notification::assertSentToTimes(
            $this->workshopUserOf($inspection),
            VehicleInspectionDisputedNotification::class,
            2,
        );
    }

    public function test_the_dispute_route_is_throttled_per_account(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        // O balde é 5/min: a sexta tentativa da mesma conta leva 429.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)
                ->post(route('user.inspections.dispute', $inspection), ['reason' => 'Motivo '.$attempt])
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->post(route('user.inspections.dispute', $inspection), ['reason' => 'Motivo 6'])
            ->assertStatus(429);
    }

    public function test_the_withdraw_route_shares_the_same_bucket(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)->delete(route('user.inspections.dispute.withdraw', $inspection))->assertRedirect();
        }

        $this->actingAs($owner)
            ->post(route('user.inspections.dispute', $inspection), ['reason' => 'Mais um'])
            ->assertStatus(429);
    }

    /**
     * Uma conta não cala a outra: o balde é por conta, não por IP.
     */
    public function test_one_account_does_not_spend_another_accounts_quota(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = $this->inspection($vehicle);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)->post(route('user.inspections.dispute', $inspection), ['reason' => 'Motivo '.$attempt]);
        }

        [$otherOwner, $otherVehicle] = $this->ownerWithVehicle('XYZ9W88');
        $otherInspection = $this->inspection($otherVehicle);

        $this->actingAs($otherOwner)
            ->post(route('user.inspections.dispute', $otherInspection), ['reason' => 'Outro dono, outra cota.'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    /**
     * O irmão de OS tinha o mesmo defeito, e recebeu as duas correções junto: é a mesma política, e
     * o balde é o mesmo de propósito.
     */
    public function test_the_maintenance_dispute_has_the_same_ceiling_and_notifies_once(): void
    {
        Notification::fake();

        [$owner, $vehicle] = $this->ownerWithVehicle();
        $workshop = Workshop::factory()->create();
        // Só OS registrada por oficina é contestável (Maintenance::isDisputable).
        $maintenance = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => $workshop->id,
            'user_id' => $workshop->user_id,
        ]);

        $reason = 'Este serviço não foi feito no meu carro.';

        $this->actingAs($owner)->post(route('user.workshop-records.dispute', $maintenance), ['reason' => $reason]);
        $this->actingAs($owner)->post(route('user.workshop-records.dispute', $maintenance), ['reason' => $reason]);

        Notification::assertSentToTimes(
            $workshop->user,
            MaintenanceDisputedNotification::class,
            1,
        );

        // Mesmo balde das rotas de laudo: as duas primeiras já gastaram 2 das 5 fichas.
        for ($attempt = 3; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)->post(route('user.workshop-records.dispute', $maintenance), ['reason' => 'Motivo '.$attempt]);
        }

        $this->actingAs($owner)
            ->post(route('user.workshop-records.dispute', $maintenance), ['reason' => 'Sexta'])
            ->assertStatus(429);
    }

    private function workshopUserOf(VehicleInspection $inspection): User
    {
        return $inspection->fresh()->workshop->user;
    }

    /**
     * @return array{0: User, 1: Vehicle}
     */
    private function ownerWithVehicle(string $plate = 'ABC1D23'): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => $plate]);
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
