<?php

namespace Tests\Unit;

use App\Enums\RegistrationSource;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeUserMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_mail_uses_revisalog_copy_and_dashboard_cta(): void
    {
        config([
            'app.name' => 'vehicle_maintenance',
            'mail.from.name' => 'Revisalog',
        ]);

        $user = User::factory()->asUser()->create(['name' => 'João Silva']);

        $mailable = new WelcomeUserMail($user, RegistrationSource::Web);

        $mailable->assertHasSubject('Bem-vindo à Revisalog');
        $mailable->assertHasReplyTo((string) config('mail.reply_to.address'));
        $mailable->assertSeeInHtml('João');
        $mailable->assertSeeInHtml('no veículo');
        $mailable->assertSeeInHtml('Revisalog');
        $mailable->assertSeeInHtml('Todos os direitos reservados');
        $mailable->assertDontSeeInHtml('Vehicle Maintenance');
        $mailable->assertDontSeeInHtml('vehicle_maintenance');
        $mailable->assertSeeInHtml(route('user.dashboard'));
    }
}
