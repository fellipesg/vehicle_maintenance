<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageDispatch;
use App\Models\WorkshopMessageTemplate;
use App\Notifications\WorkshopFollowUpNotification;
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
}
