<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceKmReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceKmReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return [
            'interval_kilometers' => 10_000,
            'anchor_kilometers' => 40_000,
            'next_due_kilometers' => 50_000,
            'kilometers_remaining' => 1_400,
            'is_overdue' => false,
            'progress_percent' => 86.0,
            'notify_before_kilometers' => 2_000,
        ];
    }

    public function test_mail_greeting_uses_owner_first_name(): void
    {
        $user = User::factory()->asUser()->create(['name' => 'Felipe Gonçalves']);
        $vehicle = Vehicle::factory()->create();

        $mail = (new MaintenanceKmReminderNotification($vehicle, $this->summary()))
            ->toMail($user);

        $this->assertSame('Olá, Felipe!', $mail->greeting);
    }

    public function test_database_payload_uses_relative_vehicle_url(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();

        $payload = (new MaintenanceKmReminderNotification($vehicle, $this->summary()))
            ->toArray($user);

        $this->assertStringStartsWith('/', $payload['vehicle_url']);
        $this->assertStringContainsString((string) $vehicle->id, $payload['vehicle_url']);
    }
}
