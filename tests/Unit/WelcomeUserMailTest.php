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

    public function test_welcome_mail_uses_revisalog_copy_and_first_step_cta(): void
    {
        config([
            'app.name' => 'vehicle_maintenance',
            'mail.from.name' => 'Vehicle Maintenance System',
            'mail.from.address' => 'fgoncalves2008@gmail.com',
        ]);

        $user = User::factory()->asUser()->create(['name' => 'João Silva']);

        $mailable = new WelcomeUserMail($user, RegistrationSource::Web);

        $this->assertTrue($mailable->hasFrom('noreply@revisalog.com.br', 'RevisaLog'));
        $mailable->assertHasSubject('Bem-vindo à RevisaLog');
        $mailable->assertHasReplyTo((string) config('mail.reply_to.address'));
        $mailable->assertSeeInHtml('João');
        $mailable->assertSeeInHtml('no veículo');
        $mailable->assertSeeInHtml('RevisaLog');
        $mailable->assertSeeInHtml('lockup-horizontal.png');
        $mailable->assertSeeInHtml('Todos os direitos reservados');
        $mailable->assertDontSeeInHtml('Vehicle Maintenance');
        $mailable->assertDontSeeInHtml('Vehicle Maintenance System');
        $mailable->assertDontSeeInHtml('vehicle_maintenance');
        $mailable->assertSeeInHtml('href="'.route('user.vehicles.create').'"', false);
        $mailable->assertSeeInHtml('Adicionar meu veículo');
        $mailable->assertDontSeeInHtml('ainda não tem senha');
    }

    public function test_welcome_mail_lists_next_steps_with_the_provenance_terms(): void
    {
        $mailable = new WelcomeUserMail(User::factory()->asUser()->create(), RegistrationSource::Web);

        $mailable->assertSeeInOrderInHtml(['Por onde começar', 'Adicione seu veículo', 'Registre as manutenções', 'Declarada pelo proprietário', 'Peça o Selo da oficina'], false);
        $mailable->assertSeeInText('Selo da oficina');
    }

    public function test_oauth_welcome_mail_sends_to_password_setup_instead_of_the_password_login(): void
    {
        $user = User::factory()->asUser()->create(['email' => 'google@example.com']);

        $mailable = new WelcomeUserMail($user, RegistrationSource::Oauth);

        $this->assertTrue($mailable->needsPassword());
        $this->assertSame(route('password.request', ['portal' => 'usuario']), $mailable->actionUrl());
        $mailable->assertSeeInHtml('href="'.e(route('password.request', ['portal' => 'usuario'])).'"', false);
        $mailable->assertSeeInHtml('Definir senha para o navegador');
        $mailable->assertSeeInHtml('ainda não tem senha');
        $mailable->assertSeeInHtml('google@example.com');
        $mailable->assertDontSeeInHtml('href="'.route('login.usuario').'"', false);
    }

    public function test_app_welcome_mail_with_password_points_to_the_browser_login(): void
    {
        $mailable = new WelcomeUserMail(User::factory()->asUser()->create(), RegistrationSource::Api);

        $this->assertFalse($mailable->needsPassword());
        $mailable->assertSeeInHtml('href="'.route('login.usuario').'"', false);
        $mailable->assertSeeInHtml('Entrar pelo navegador');
        $mailable->assertSeeInHtml('mesmo e-mail e a mesma senha');
    }
}
