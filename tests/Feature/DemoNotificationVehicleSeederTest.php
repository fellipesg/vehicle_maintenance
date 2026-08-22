<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use Database\Seeders\DemoNotificationVehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoNotificationVehicleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_two_vehicles_within_notification_threshold(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'fgoncalves2008@gmail.com',
        ]);

        $this->seed(DemoNotificationVehicleSeeder::class);

        $reminders = app(VehicleMaintenanceReminderService::class);

        $nearFifty = $user->currentVehicles()->where('license_plate', 'NTF5A00')->first();
        $nearHundred = $user->currentVehicles()->where('license_plate', 'NTF9A00')->first();

        $this->assertNotNull($nearFifty);
        $this->assertNotNull($nearHundred);

        $nearFifty->load('maintenances');
        $nearHundred->load('maintenances');

        $this->assertSame(48_600, (int) $nearFifty->current_kilometers);
        $this->assertSame(98_700, (int) $nearHundred->current_kilometers);
        $this->assertTrue($reminders->shouldNotify($nearFifty));
        $this->assertTrue($reminders->shouldNotify($nearHundred));
        $this->assertSame(50_000, $reminders->summarize($nearFifty)['next_due_kilometers']);
        $this->assertSame(100_000, $reminders->summarize($nearHundred)['next_due_kilometers']);
    }
}
