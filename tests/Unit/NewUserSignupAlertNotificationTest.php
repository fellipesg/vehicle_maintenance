<?php

namespace Tests\Unit;

use App\Enums\RegistrationSource;
use App\Models\User;
use App\Notifications\NewUserSignupAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewUserSignupAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_alert_uses_revisalog_from_and_salutation(): void
    {
        config([
            'app.name' => 'vehicle_maintenance',
            'mail.from.name' => 'Vehicle Maintenance System',
            'mail.from.address' => 'fgoncalves2008@gmail.com',
        ]);

        $user = User::factory()->asUser()->create([
            'name' => 'Cursor Agent',
            'email' => 'fgoncalves2008+from@gmail.com',
        ]);

        $mail = (new NewUserSignupAlertNotification($user, RegistrationSource::Web))
            ->toMail($user);

        $this->assertSame(['noreply@revisalog.com.br', 'RevisaLog'], $mail->from);
        $this->assertSame('RevisaLog', $mail->salutation);
        $this->assertSame('Novo cadastro — Cursor Agent', $mail->subject);

        $html = $mail->render();

        $this->assertStringContainsString('Novo cadastro na RevisaLog', $html);
        $this->assertStringContainsString('Cursor Agent', $html);
        $this->assertStringContainsString('fgoncalves2008+from@gmail.com', $html);
        $this->assertStringContainsString('RevisaLog', $html);
        $this->assertStringContainsString('lockup-horizontal.png', $html);
        $this->assertStringNotContainsString('Vehicle Maintenance', $html);
        $this->assertStringNotContainsString('Vehicle Maintenance System', $html);
        $this->assertStringNotContainsString('vehicle_maintenance', $html);
    }
}
