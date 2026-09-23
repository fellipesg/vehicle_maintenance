<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CrlvImportFailureMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Falha do provedor vira nova tentativa, não aviso perdido.
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $reason,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CRLV-e não lido — '.$this->reason,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crlv-import-failure',
        );
    }
}
