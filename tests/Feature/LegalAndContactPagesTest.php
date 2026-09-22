<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LegalAndContactPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_links_to_legal_pages_and_contact(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'))
            ->assertSee(route('contact.show'))
            ->assertSee('mailto:contato@revisalog.com.br', false)
            ->assertSee('mailto:privacidade@revisalog.com.br', false);
    }

    public function test_terms_page_renders_config_text(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Termos de Uso')
            ->assertSee('Responsabilidade pelas informações');
    }

    public function test_privacy_page_renders_sections_and_links_privacy_email(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Política de Privacidade')
            ->assertSee('7. Seus direitos')
            ->assertSee('href="mailto:privacidade@revisalog.com.br"', false);
    }

    public function test_privacy_policy_is_available_to_the_app(): void
    {
        $this->getJson('/api/v1/legal/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.version', config('legal.privacy_version'));
    }

    public function test_contact_message_is_mailed_with_reply_to_sender(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'subject' => 'support',
            'message' => 'Não consigo importar o CRLV do meu carro.',
        ])->assertRedirect(route('contact.show'))->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo('contato@revisalog.com.br')
                && $mail->hasReplyTo('ana@example.com')
                && $mail->subjectLabel === 'Suporte';
        });
    }

    public function test_privacy_subject_goes_to_privacy_inbox(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'subject' => 'privacy',
            'message' => 'Quero excluir meus dados pessoais.',
        ])->assertRedirect(route('contact.show'));

        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('privacidade@revisalog.com.br'));
    }

    public function test_honeypot_submission_is_silently_dropped(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'question',
            'message' => 'Compre seguidores baratos agora mesmo.',
            'website' => 'https://spam.example',
        ])->assertRedirect(route('contact.show'))->assertSessionHas('success');

        Mail::assertNothingOutgoing();
    }

    public function test_invalid_contact_message_is_rejected(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), ['subject' => 'unknown'])
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        Mail::assertNothingOutgoing();
    }

    public function test_turnstile_is_enforced_when_configured(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret', 'services.turnstile.site_key' => 'site']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->get(route('contact.show'))->assertSee('data-sitekey="site"', false);

        $this->post(route('contact.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'subject' => 'question',
            'message' => 'Mensagem com captcha inválido.',
            'cf-turnstile-response' => 'token',
        ])->assertSessionHasErrors('cf-turnstile-response');

        Mail::assertNothingOutgoing();
    }

    public function test_turnstile_outage_is_a_validation_error_not_a_crash(): void
    {
        Mail::fake();
        config(['services.turnstile.secret_key' => 'secret']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->from(route('contact.show'))->post(route('contact.store'), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'subject' => 'question',
            'message' => 'Mensagem durante instabilidade da Cloudflare.',
            'cf-turnstile-response' => 'token',
        ])->assertRedirect(route('contact.show'))
            ->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionHasInput('message');

        Mail::assertNothingOutgoing();
    }
}
