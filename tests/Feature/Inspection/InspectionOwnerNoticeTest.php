<?php

namespace Tests\Feature\Inspection;

use App\Enums\InspectionVerdict;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\Workshop;
use App\Models\WorkshopMessageOptOut;
use App\Notifications\VehicleInspectionIssuedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O dono atual é avisado quando uma oficina emite um laudo sobre o carro dele.
 *
 * O par que o produto promete é descoberta + resposta: a contestação existe desde o PR #78, mas sem
 * este aviso o proprietário só achava um "Reprovado" sobre o próprio carro se abrisse a aba
 * Vistorias por iniciativa própria. O irmão da OS (WorkshopRecordRegisteredNotification) já fazia
 * isso, e é o mesmo notificador — não um segundo mecanismo.
 */
class InspectionOwnerNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_owner_is_notified_when_a_workshop_issues_a_report(): void
    {
        Notification::fake();

        [$user] = $this->workshop();
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => $vehicle->license_plate]))
            ->assertRedirect();

        $inspection = VehicleInspection::query()->sole();

        Notification::assertSentTo(
            $owner,
            VehicleInspectionIssuedNotification::class,
            function (VehicleInspectionIssuedNotification $notification) use ($inspection, $owner): bool {
                $payload = $notification->toArray($owner);

                $this->assertSame('inspection_issued', $payload['type']);
                $this->assertSame($inspection->id, $payload['inspection_id']);
                $this->assertSame($inspection->vehicle_id, $payload['vehicle_id']);

                // O aviso manda o dono para a aba onde ele encontra o laudo e o botão de contestar.
                $mail = $notification->toMail($owner)->toArray();
                $this->assertStringContainsString('#vistorias', (string) $mail['actionUrl']);

                return true;
            },
        );
    }

    /**
     * O e-mail não pode prometer que o laudo sai de circulação enquanto a contestação está aberta:
     * contestar marca e não esconde. É o mesmo cuidado que MaintenanceDisputedNotification carrega
     * desde 19/11/2026 (.ai/rules/notifications.md).
     */
    public function test_the_mail_says_the_report_stays_in_the_history(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $inspection = VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
        ]);

        $mail = (new VehicleInspectionIssuedNotification($inspection->fresh()))->toMail($owner)->toArray();
        $lines = implode(' ', array_map('strval', $mail['introLines']));

        $this->assertStringContainsString('Vistoria Confiável', $lines);
        $this->assertStringContainsString('continua visível', $lines);
        $this->assertStringContainsString($inspection->verification_code, $lines);
        $this->assertSame('RevisaLog', $mail['salutation']);
    }

    /**
     * A notificação é ShouldQueue, e o SerializesModels devolve o laudo **sem** relações dentro do
     * job. Com Model::preventLazyLoading ligado fora de produção, montar o texto a partir de
     * `$inspection->vehicle` estouraria no worker em vez de mandar o e-mail — daí o loadMissing.
     */
    public function test_the_mail_is_built_from_a_model_without_loaded_relations(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();
        VehicleInspection::factory()->create([
            'vehicle_id' => $vehicle->id,
            'workshop_id' => Workshop::factory()->create(['name' => 'Vistoria Confiável'])->id,
            'workshop_name' => 'Vistoria Confiável',
        ]);

        // Como a fila devolve: consulta nova, nenhuma relação carregada.
        $fromQueue = VehicleInspection::query()->sole();
        $this->assertFalse($fromQueue->relationLoaded('vehicle'));

        $body = (new VehicleInspectionIssuedNotification($fromQueue))->body();

        $this->assertStringContainsString('Vistoria Confiável', $body);
        $this->assertStringContainsString(trim($vehicle->brand.' '.$vehicle->model), $body);
    }

    /**
     * Conta de lojista não recebe aviso de registro de oficina, como no irmão da OS: a aba de
     * registros é do Proprietário (.ai/rules/notifications.md).
     */
    public function test_a_garage_account_is_not_notified(): void
    {
        Notification::fake();

        [$user] = $this->workshop();
        $garage = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'GAR1A23']);
        $this->attachVehicleToUser($garage, $vehicle);

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => 'GAR1A23']))
            ->assertRedirect();

        Notification::assertNothingSentTo($garage);
    }

    /**
     * Veículo criado pelo chassi junto com o laudo não tem dono: não há a quem avisar, e a emissão
     * não pode quebrar por isso.
     */
    public function test_an_ownerless_vehicle_has_nobody_to_notify(): void
    {
        Notification::fake();

        [$user] = $this->workshop();

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload([
                'chassis' => '9BWZZZ377VT004321',
                'new_vehicle' => ['brand' => 'Fiat', 'model' => 'Argo', 'year' => '2021'],
            ]))
            ->assertRedirect();

        $this->assertDatabaseCount('vehicle_inspections', 1);
        Notification::assertNotSentTo([$user], VehicleInspectionIssuedNotification::class);
    }

    /**
     * O descadastro do PR #79 (workshop_message_opt_outs) é das mensagens que a **oficina escreve e
     * dispara** ao cliente. Este aviso não é da oficina: é o produto informando o proprietário de
     * que alguém afirmou algo sobre o bem dele, e é a única porta de entrada para o direito de
     * resposta — calá-lo por um opt-out de marketing tiraria em silêncio a chance de contestar.
     * O irmão da OS também não confere, e a decisão é a mesma nos dois.
     */
    public function test_the_notice_ignores_the_workshop_message_opt_out(): void
    {
        Notification::fake();

        [$user, $workshop] = $this->workshop();
        [$owner, $vehicle] = $this->ownerWithVehicle();

        // Descadastro total: nenhuma oficina pode mandar mensagem a este destinatário.
        WorkshopMessageOptOut::query()->create(['user_id' => $owner->id, 'workshop_id' => null]);
        WorkshopMessageOptOut::query()->create(['user_id' => $owner->id, 'workshop_id' => $workshop->id]);

        $this->actingAs($user->fresh())
            ->post('/oficina/vistorias', $this->payload(['license_plate' => $vehicle->license_plate]))
            ->assertRedirect();

        Notification::assertSentTo($owner, VehicleInspectionIssuedNotification::class);
    }

    /**
     * UserFactory::configure() já cria a oficina (aprovada) da conta de oficina, e a flag de emissão
     * vem do helper da TestCase — emitir exige as duas coisas (.ai/rules/inspections.md).
     *
     * @return array{0: User, 1: Workshop}
     */
    private function workshop(): array
    {
        $this->enableVehicleInspectionsForEveryone();

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
            'inspected_at' => now()->subDay()->toDateString(),
            'verdict' => InspectionVerdict::ApprovedWithRestrictions->value,
        ], $overrides);
    }
}
