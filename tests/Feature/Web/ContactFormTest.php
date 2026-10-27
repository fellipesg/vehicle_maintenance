<?php

namespace Tests\Feature\Web;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    public function test_contact_page_shows_support_email(): void
    {
        $this->get(route('contact.show'))
            ->assertOk()
            ->assertSee((string) config('legal.support_email'))
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="message"', false)
            ->assertDontSee('cf-turnstile', false);
    }

    public function test_contact_form_queues_mail_to_support(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'subject' => 'support',
            'message' => 'Preciso de ajuda com o relatório do veículo.',
        ])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo((string) config('legal.support_email'))
                && $mail->hasReplyTo('ana@example.com')
                && $mail->name === 'Ana Silva'
                && $mail->email === 'ana@example.com'
                && $mail->body === 'Preciso de ajuda com o relatório do veículo.'
                && $mail->topic === 'Suporte';
        });
    }

    public function test_contact_form_validates_required_fields(): void
    {
        Mail::fake();

        $this->from(route('contact.show'))
            ->post(route('contact.store'), [])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        Mail::assertNothingOutgoing();
    }

    public function test_contact_honeypot_pretends_success_without_sending(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'question',
            'message' => 'spam',
            'website' => 'https://spam.example',
        ])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        Mail::assertNothingOutgoing();
    }

    public function test_contact_subject_is_preselected_from_query_string(): void
    {
        $this->get(route('contact.show', ['assunto' => 'privacy']))
            ->assertOk()
            ->assertSee('<option value="privacy" selected', false);
    }

    public function test_contact_rejects_unknown_subject(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'subject' => 'unknown',
            'message' => 'Mensagem qualquer.',
        ])->assertSessionHasErrors('subject');

        Mail::assertNothingOutgoing();
    }

    public function test_turnstile_is_enforced_when_configured(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret', 'services.turnstile.site_key' => 'site']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->get(route('contact.show'))->assertSee('data-sitekey="site"', false);

        $this->post(route('contact.store'), $this->validPayload(['cf-turnstile-response' => 'token']))
            ->assertSessionHasErrors('cf-turnstile-response');

        Mail::assertNothingOutgoing();
    }

    public function test_turnstile_accepts_valid_token(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post(route('contact.store'), $this->validPayload(['cf-turnstile-response' => 'token']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class);
    }

    public function test_turnstile_outage_is_a_validation_error_not_a_crash(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->from(route('contact.show'))
            ->post(route('contact.store'), $this->validPayload(['cf-turnstile-response' => 'token']))
            ->assertRedirect(route('contact.show'))
            ->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionHasInput('message');

        Mail::assertNothingOutgoing();
    }

    /**
     * O defeito que isto cobre: a regra do captcha só entrava quando a chave existia, então
     * TURNSTILE_SECRET_KEY faltando em produção desligava a proteção sem nenhum sinal. Agora
     * produção exige sempre: o envio é recusado e a falta da chave vira exceção reportada.
     */
    public function test_production_without_the_turnstile_key_refuses_and_reports(): void
    {
        Mail::fake();
        Exceptions::fake(RuntimeException::class);
        Http::preventStrayRequests();
        config(['services.turnstile.secret_key' => null]);
        $this->actAsProduction();

        $this->from(route('contact.show'))
            ->post(route('contact.store'), $this->validPayload(['cf-turnstile-response' => 'token']))
            ->assertRedirect(route('contact.show'))
            ->assertSessionHasErrors('cf-turnstile-response');

        Mail::assertNothingOutgoing();
        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'TURNSTILE_SECRET_KEY'),
        );
    }

    /** Sem a chave, produção nem chega a consultar a Cloudflare: o campo já é obrigatório. */
    public function test_production_requires_the_captcha_field_even_without_the_key(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.turnstile.secret_key' => null]);
        $this->actAsProduction();

        $this->post(route('contact.store'), $this->validPayload())
            ->assertSessionHasErrors(['cf-turnstile-response' => 'Confirme que você não é um robô.']);

        Mail::assertNothingOutgoing();
    }

    /** Em local e testing, sem a chave, nada muda: o captcha segue desligado. */
    public function test_outside_production_the_missing_key_keeps_the_captcha_off(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.turnstile.secret_key' => null]);

        $this->post(route('contact.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class);
    }

    /** Com a chave no lugar, produção verifica o token como em qualquer ambiente. */
    public function test_production_with_the_key_verifies_the_token(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->actAsProduction();

        $this->post(route('contact.store'), $this->validPayload(['cf-turnstile-response' => 'token']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class);
    }

    public function test_sixth_message_from_the_same_ip_in_a_minute_returns_429(): void
    {
        Mail::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('contact.store'), $this->validPayload())
                ->assertRedirect(route('contact.show'));
        }

        $this->post(route('contact.store'), $this->validPayload())->assertStatus(429);

        Mail::assertQueued(ContactMessageMail::class, 5);
    }

    /**
     * A chave do captcha vem do ambiente e pode sumir sem avisar, então `php artisan about` diz se
     * ela está lá — o sinal visível que faltava quando a proteção se desligava em silêncio.
     */
    public function test_about_command_reports_whether_the_captcha_key_is_configured(): void
    {
        config(['services.turnstile.secret_key' => 'secret']);
        $this->artisan('about --only=revisalog')
            ->expectsOutputToContain('configurado')
            ->assertExitCode(0);

        config(['services.turnstile.secret_key' => null]);
        $this->artisan('about --only=revisalog')
            ->expectsOutputToContain('SEM CHAVE')
            ->assertExitCode(0);
    }

    /**
     * Produção dentro do teste. Trocar o ambiente também religa a verificação de CSRF, porque
     * Application::runningUnitTests() olha justamente o env — e CSRF não é o assunto destes testes.
     */
    private function actAsProduction(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'subject' => 'question',
            'message' => 'Como funciona o selo da oficina?',
        ], $overrides);
    }
}
