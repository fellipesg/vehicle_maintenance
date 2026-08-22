<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceKmReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckMaintenanceKmRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_reminder_when_vehicle_is_within_threshold(): void
    {
        Mail::fake();

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 98_500,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2024-01-10',
        ]);

        $this->artisan('maintenance:check-km-reminders')
            ->expectsOutputToContain('Lembretes enviados: 1')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 1);

        $notification = $user->fresh()->notifications->first();
        $this->assertSame(MaintenanceKmReminderNotification::class, $notification->type);
        $this->assertSame($vehicle->id, $notification->data['vehicle_id']);
        $this->assertSame(100_000, $notification->data['next_due_kilometers']);
    }

    public function test_command_does_not_send_duplicate_for_same_due_milestone(): void
    {
        Mail::fake();

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 98_500,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2024-01-10',
        ]);

        $this->artisan('maintenance:check-km-reminders')->assertExitCode(0);
        $this->artisan('maintenance:check-km-reminders')
            ->expectsOutputToContain('Lembretes enviados: 0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_command_skips_vehicle_outside_notification_threshold(): void
    {
        Notification::fake();

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 85_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'kilometers' => 80_000,
            'maintenance_date' => '2024-01-10',
        ]);

        $this->artisan('maintenance:check-km-reminders')
            ->expectsOutputToContain('Lembretes enviados: 0')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 98_500,
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $user->notify(new MaintenanceKmReminderNotification($vehicle, [
            'interval_kilometers' => 10_000,
            'anchor_kilometers' => 80_000,
            'next_due_kilometers' => 100_000,
            'kilometers_remaining' => 1_500,
            'is_overdue' => false,
            'progress_percent' => 85.0,
            'notify_before_kilometers' => 2_000,
        ]));

        $notification = $user->fresh()->unreadNotifications->first();
        $this->assertNotNull($notification);

        $response = $this->actingAs($user)
            ->post(route('notifications.read', $notification->id));

        $response->assertRedirect(route('user.vehicles.show', $vehicle));
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
