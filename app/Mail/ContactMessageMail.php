<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $subjectLabel,
        public string $body,
        public ?int $userId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: "[Fale conosco] {$this->subjectLabel} — {$this->senderName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.contact-message',
        );
    }
}
