<?php

namespace Tests\Feature;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageDispatch;
use App\Models\WorkshopMessageOptOut;
use App\Models\WorkshopMessageTemplate;
use App\Notifications\WorkshopFollowUpNotification;
use App\Services\FcmService;
use App\Services\Workshop\WorkshopMessageTemplateDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkshopFollowUpDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dispatches_scheduled_revision_follow_up_to_current_owner(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = Workshop::factory()->forUser($workshopUser)->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
            'service_category' => 'mechanical',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(1, $sent);
        $this->assertDatabaseCount('workshop_message_dispatches', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => WorkshopFollowUpNotification::class,
        ]);
    }

    public function test_dispatches_corrective_follow_up_after_min_days(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create();
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshop = Workshop::factory()->forUser($workshopUser)->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 55_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 50_000,
            'maintenance_date' => '2026-03-01',
            'service_category' => 'mechanical',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->correctiveFollowUp()->create([
            'min_days_since_service' => 60,
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('workshop_message_dispatches', [
            'vehicle_id' => $vehicle->id,
            'dedupe_key' => (string) $maintenance->id,
        ]);
    }

    public function test_skips_vehicle_never_serviced_by_workshop(): void
    {
        Notification::fake();

        $owner = User::factory()->asUser()->create();
        $servicingWorkshop = Workshop::factory()->create();
        $otherWorkshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 98_000]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $servicingWorkshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($otherWorkshop)->scheduledRevision()->create();

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_skips_duplicate_scheduled_dispatch_for_same_milestone(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 70_000]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        $template = WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        WorkshopMessageDispatch::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'template_id' => $template->id,
            'dedupe_key' => '100000',
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(0, $sent);
        $this->assertDatabaseCount('workshop_message_dispatches', 1);
    }

    public function test_command_runs_workshop_follow_ups_after_owner_reminders(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create();

        $this->artisan('maintenance:check-km-reminders')
            ->expectsOutputToContain('Follow-ups de oficina enviados: 1')
            ->assertExitCode(0);
    }

    public function test_owner_lookup_does_not_run_once_per_vehicle(): void
    {
        Mail::fake();

        $workshop = Workshop::factory()->create();

        foreach (range(1, 4) as $index) {
            $owner = User::factory()->asUser()->create();
            $vehicle = Vehicle::factory()->create([
                'current_kilometers' => 70_000,
                'odometer_at_registration' => 70_000,
            ]);
            $this->attachVehicleToUser($owner, $vehicle);

            Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $workshop->id,
                'kilometers' => 80_000,
                'maintenance_date' => '2025-01-01',
            ]);
        }

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        $statements = [];
        DB::listen(static function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $ownerLookups = count(array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'user_vehicles')));

        $this->assertSame(4, $sent);
        $this->assertLessThanOrEqual(
            2,
            $ownerLookups,
            'Os donos do lote saem em uma consulta, não em uma por veículo por template.',
        );
    }

    public function test_a_template_whose_trigger_has_no_round_does_not_take_the_run_down(): void
    {
        Mail::fake();

        $workshop = Workshop::factory()->create();
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        // Gatilho sem rodada, ativo e com id menor: no `match` sem default ele estourava
        // UnhandledMatchError e levava a rodada de todas as oficinas, inclusive a de baixo.
        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->create([
            'trigger' => WorkshopMessageTrigger::WarrantyExpiring,
            'is_active' => true,
        ]);
        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 1);
    }

    public function test_a_service_older_than_the_send_window_gets_no_message(): void
    {
        Mail::fake();

        $workshop = Workshop::factory()->create();
        $this->assertSame(60, config('workshop_messages.send_window_days'));

        // min_days 60 + janela 60: a OS de 2026-03-01 (92 dias) está dentro, a de 2025-01-01 não.
        $inside = $this->servicedVehicle($workshop, '2026-03-01');
        $outside = $this->servicedVehicle($workshop, '2025-01-01');

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->correctiveFollowUp()->create([
            'min_days_since_service' => 60,
        ]);

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseHas('workshop_message_dispatches', ['vehicle_id' => $inside->vehicle_id]);
        $this->assertDatabaseMissing('workshop_message_dispatches', ['vehicle_id' => $outside->vehicle_id]);
    }

    public function test_the_customer_waits_the_minimum_interval_between_two_messages(): void
    {
        Mail::fake();

        $this->assertSame(7, config('workshop_messages.min_days_between_messages_per_user'));

        $workshop = Workshop::factory()->create();
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        // Outra oficina falou com esta pessoa dois dias atrás: o intervalo soma todas as oficinas.
        WorkshopMessageDispatch::factory()->create([
            'user_id' => $owner->id,
            'sent_at' => now()->subDays(2),
        ]);

        $this->assertSame(0, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 1);

        // E não é castigo: passado o intervalo, a mesma oportunidade ainda sai.
        Carbon::setTestNow('2026-06-09');

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 2);
    }

    public function test_two_workshops_do_not_both_reach_the_same_customer_in_one_run(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        foreach (range(1, 2) as $index) {
            $workshop = Workshop::factory()->create();

            Maintenance::factory()->create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $owner->id,
                'tenant_id' => $owner->tenant_id,
                'workshop_id' => $workshop->id,
                'kilometers' => 80_000,
                'maintenance_date' => '2025-01-01',
            ]);

            WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
                'lead_kilometers' => 2_000,
            ]);
        }

        // As duas oficinas atenderam o carro e as duas têm modelo ativo: a pessoa recebe uma só.
        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        $this->assertDatabaseCount('workshop_message_dispatches', 1);
    }

    /**
     * Veículo com dono vinculado e uma OS da oficina na data pedida.
     */
    private function servicedVehicle(Workshop $workshop, string $maintenanceDate): Maintenance
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 55_000]);
        $this->attachVehicleToUser($owner, $vehicle);

        return Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 50_000,
            'maintenance_date' => $maintenanceDate,
            'service_category' => 'mechanical',
        ]);
    }

    public function test_the_workshop_name_reaches_the_notification_without_double_escaping(): void
    {
        Mail::fake();

        $owner = User::factory()->asUser()->create(['name' => 'Maria Souza']);
        $workshop = Workshop::factory()->create(['name' => 'José & Filhos']);
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->create([
            'title' => 'Revisão na {{workshop_name}}',
            'body' => 'Olá {{customer_name}}, a {{workshop_name}} espera seu {{vehicle}}.',
            'lead_kilometers' => 2_000,
        ]);

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());

        $data = json_decode((string) DB::table('notifications')->value('data'), true);

        $this->assertSame('Revisão na José & Filhos', $data['title']);
        $this->assertStringContainsString('a José & Filhos espera', $data['body']);
        $this->assertStringNotContainsString('&amp;', $data['body'].$data['title']);
    }

    public function test_does_not_send_anything_to_an_owner_opted_out_of_the_workshop(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldNotReceive('sendToUser');

        ['owner' => $owner, 'workshop' => $workshop] = $this->scheduledFollowUpScenario();
        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('workshop_message_dispatches', 0);
    }

    public function test_global_opt_out_silences_every_workshop(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldNotReceive('sendToUser');

        ['owner' => $owner] = $this->scheduledFollowUpScenario();
        WorkshopMessageOptOut::factory()->everyWorkshop()->create(['user_id' => $owner->id]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('workshop_message_dispatches', 0);
    }

    public function test_opt_out_of_another_workshop_does_not_silence_this_one(): void
    {
        Mail::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once()->andReturnTrue();

        ['owner' => $owner] = $this->scheduledFollowUpScenario();
        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => Workshop::factory()->create()->id,
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(1, $sent);
        $this->assertDatabaseCount('workshop_message_dispatches', 1);
    }

    public function test_opt_out_of_one_workshop_leaves_another_owner_of_the_same_vehicle_receiving(): void
    {
        Mail::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once()->andReturnTrue();

        ['owner' => $owner, 'workshop' => $workshop, 'vehicle' => $vehicle] = $this->scheduledFollowUpScenario();
        $otherOwner = User::factory()->asUser()->create();
        $this->attachVehicleToUser($otherOwner, $vehicle);

        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);

        $sent = app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('workshop_message_dispatches', ['user_id' => $otherOwner->id]);
        $this->assertDatabaseMissing('workshop_message_dispatches', ['user_id' => $owner->id]);
    }

    public function test_corrective_follow_up_also_respects_the_opt_out(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldNotReceive('sendToUser');

        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create(['current_kilometers' => 55_000]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 50_000,
            'maintenance_date' => '2026-03-01',
            'service_category' => 'mechanical',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->correctiveFollowUp()->create([
            'min_days_since_service' => 60,
        ]);

        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);

        $this->assertSame(0, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());
        Notification::assertNothingSent();
    }

    /**
     * Oficina com template de revisão programada, veículo a 10.000 km da próxima revisão e um dono
     * atual — o cenário mínimo em que o follow-up sai.
     *
     * @return array{owner: User, workshop: Workshop, vehicle: Vehicle}
     */
    private function scheduledFollowUpScenario(): array
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
            'service_category' => 'mechanical',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        return ['owner' => $owner, 'workshop' => $workshop, 'vehicle' => $vehicle];
    }
}
