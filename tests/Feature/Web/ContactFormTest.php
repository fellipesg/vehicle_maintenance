<?php

namespace Tests\Feature\Web;

use App\Mail\ContactMessageMail;
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
            ->assertSee('name="message"', false);
    }

    public function test_contact_form_queues_mail_to_support(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'message' => 'Preciso de ajuda com o relatório do veículo.',
        ])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo((string) config('legal.support_email'))
                && $mail->hasReplyTo('ana@example.com')
                && $mail->name === 'Ana Silva'
                && $mail->email === 'ana@example.com'
                && $mail->body === 'Preciso de ajuda com o relatório do veículo.';
        });
    }

    public function test_contact_form_validates_required_fields(): void
    {
        Mail::fake();

        $this->from(route('contact.show'))
            ->post(route('contact.store'), [])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingOutgoing();
    }

    public function test_contact_honeypot_pretends_success_without_sending(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'spam',
            'website' => 'https://spam.example',
        ])
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        Mail::assertNothingOutgoing();
    }
}
