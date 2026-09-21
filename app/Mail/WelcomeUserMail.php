<?php

namespace App\Mail;

use App\Enums\RegistrationSource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class WelcomeUserMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public RegistrationSource $source,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address(
                    (string) config('mail.reply_to.address'),
                    (string) config('mail.reply_to.name'),
                ),
            ],
            subject: 'Bem-vindo à Revisalog',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.welcome-user',
            with: [
                'firstName' => $this->firstName(),
                'actionUrl' => $this->actionUrl(),
            ],
        );
    }

    public function firstName(): string
    {
        $firstName = trim((string) Str::of($this->user->name)->explode(' ')->first());

        return $firstName !== '' ? $firstName : 'motorista';
    }

    public function actionUrl(): string
    {
        return match ($this->source) {
            RegistrationSource::Web => route('user.dashboard'),
            RegistrationSource::Api, RegistrationSource::Oauth => route('login.usuario'),
        };
    }
}
