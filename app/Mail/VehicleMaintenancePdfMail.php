<?php

namespace App\Mail;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehicleMaintenancePdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public string $pdfContent,
        public string $filename,
        /** @var list<array{filename: string, storage_path: string, disk: string, mime: string}> */
        public array $invoiceAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Histórico de manutenções — {$this->vehicle->brand} {$this->vehicle->model}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-maintenance-pdf',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];

        foreach ($this->invoiceAttachments as $invoice) {
            $attachments[] = Attachment::fromStorageDisk($invoice['disk'], $invoice['storage_path'])
                ->as($invoice['filename'])
                ->withMime($invoice['mime']);
        }

        return $attachments;
    }
}
