<?php

namespace Tests\Feature\Web;

use App\Mail\ContactMessageMail;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
