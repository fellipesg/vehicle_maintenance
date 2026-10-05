<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Resultado da importação do CSV de oficinas (App\Jobs\ImportWorkshopProspectsCsv) para quem enviou.
 * Não é ShouldQueue: já sai de dentro do job.
 */
class WorkshopProspectImportFinishedMail extends Mailable
{
    /**
     * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
     *
     * @var string
     */
    public $theme = 'revisalog';

    /**
     * @param  array{created: int, duplicates: int, suppressed: int, customers: int, invalid: int}|null  $counts
     */
    public function __construct(
        public string $filename,
        public ?array $counts = null,
        public ?string $error = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->error === null
                ? "Importação de oficinas concluída: {$this->counts['created']} novas"
                : 'Importação de oficinas não concluída',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.outreach-import-finished',
            with: [
                'indexUrl' => route('admin.outreach.index'),
            ],
        );
    }
}
