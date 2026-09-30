<?php

namespace Tests\Feature\Mail;

use App\Enums\RegistrationSource;
use App\Mail\ContactMessageMail;
use App\Mail\CrlvImportFailureMail;
use App\Mail\VehicleMaintenancePdfMail;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\NewUserSignupAlertNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tema "revisalog" (resources/views/vendor/mail/html/themes/revisalog.css): botão teal com texto
 * escuro, faixa do cabeçalho na cor do lockup, logo na proporção do arquivo e rodapé com contraste AA.
 */
class RevisaLogMailThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mailable_uses_the_revisalog_theme(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();

        $mailables = [
            new WelcomeUserMail($user, RegistrationSource::Web),
            new VehicleMaintenancePdfMail($vehicle, '%PDF', 'historico.pdf'),
            new CrlvImportFailureMail('Estrutura não reconhecida.', ['arquivo' => 'CRLV-e.pdf']),
            new ContactMessageMail('Ana', 'ana@example.com', 'Olá', 'Parceria'),
        ];

        foreach ($mailables as $mailable) {
            $this->assertSame('revisalog', $mailable->theme, $mailable::class);
            $this->assertBrandChrome($mailable->render(), $mailable::class);
        }
    }

    public function test_primary_button_is_teal_with_dark_text(): void
    {
        $html = (new WelcomeUserMail(User::factory()->asUser()->create(), RegistrationSource::Web))->render();

        $this->assertMatchesRegularExpression('/class="button button-primary"[^>]*style="[^"]*background-color: #2ec4b6;[^"]*color: #0b1c2c;/', $html);
        $this->assertStringNotContainsString('#18181b', $html);
    }

    public function test_signup_alert_notification_uses_the_theme_and_readable_labels(): void
    {
        $user = User::factory()->asUser()->create(['name' => 'Ana Souza']);

        $mail = (new NewUserSignupAlertNotification($user, RegistrationSource::Oauth))->toMail($user);

        $this->assertSame('revisalog', $mail->theme);
        $this->assertSame('RevisaLog', $mail->salutation);

        $html = (string) $mail->render();

        $this->assertBrandChrome($html, NewUserSignupAlertNotification::class);
        $this->assertStringContainsString('Proprietário', $html);
        $this->assertStringContainsString('App (Google ou Facebook)', $html);
        $this->assertStringNotContainsString('<strong>Tipo:</strong> user', $html);
    }

    /**
     * Notificação com MailMessage não define ->theme(): o padrão vem de config('mail.markdown.theme').
     */
    public function test_mail_message_notifications_use_the_theme_by_default(): void
    {
        $user = User::factory()->asUser()->create(['name' => 'Ana Souza']);

        $this->assertSame('revisalog', config('mail.markdown.theme'));

        $mail = (new ResetPasswordNotification('token-de-teste'))->toMail($user);

        $this->assertNull($mail->theme);
        $html = (string) $mail->render();

        $this->assertBrandChrome($html, ResetPasswordNotification::class);
        $this->assertMatchesRegularExpression('/class="button button-primary"[^>]*style="[^"]*background-color: #2ec4b6;/', $html);
    }

    public function test_theme_file_keeps_the_brand_colors(): void
    {
        $css = file_get_contents(resource_path('views/vendor/mail/html/themes/revisalog.css'));

        $this->assertStringContainsString('background-color: #0b1c2c;', $css);
        $this->assertStringContainsString('color: #186b64;', $css);
        $this->assertStringNotContainsString('#a1a1aa', $css);
        $this->assertStringNotContainsString('#18181b', $css);
    }

    private function assertBrandChrome(string $html, string $source): void
    {
        $this->assertMatchesRegularExpression('/<td class="header" bgcolor="#0b1c2c"[^>]*style="[^"]*background-color: #0b1c2c;/', $html, $source);
        $this->assertMatchesRegularExpression('/<img src="[^"]*lockup-horizontal\.png" alt="RevisaLog" width="182" height="44"/', $html, $source);
        $this->assertMatchesRegularExpression('/<p style="[^"]*color: #455a6e;[^"]*">© \d{4} RevisaLog\. Todos os direitos reservados\.<\/p>/u', $html, $source);
        $this->assertStringNotContainsString('Revisalog', $html, $source);
    }
}
