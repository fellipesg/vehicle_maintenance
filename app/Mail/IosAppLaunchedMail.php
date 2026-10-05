<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * Comunicado único de que o app iOS está na App Store (users:announce-ios-app). Sai do noreply@ com
 * o tema da marca; não é ShouldQueue porque o comando envia em sequência, respeitando o limite do Resend.
 */
class IosAppLaunchedMail extends Mailable
{
    /**
     * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
     *
     * @var string
     */
    public $theme = 'revisalog';

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@revisalog.com.br', 'RevisaLog'),
            replyTo: [new Address((string) config('mail.reply_to.address'), 'RevisaLog')],
            subject: 'O RevisaLog chegou ao iPhone',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ios-app-launched',
            with: [
                'firstName' => $this->firstName(),
                'appStoreUrl' => (string) config('app.ios_app_store_url'),
            ],
        );
    }

    public function firstName(): string
    {
        $firstName = trim((string) Str::of($this->user->name)->explode(' ')->first());

        return $firstName !== '' ? $firstName : 'motorista';
    }
}
