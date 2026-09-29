<?php

namespace App\Mail;

use App\Models\Vehicle;
use App\Support\DisplayTime;
use App\Support\Vehicle\VehicleMaintenanceHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Histórico em PDF enviado ao proprietário (App\Jobs\EmailVehicleMaintenancePdf), com as notas
 * fiscais anexadas. O corpo em markdown traz o resumo de procedência da capa do PDF
 * ("N com selo · M declaradas"), a lista dos anexos e o link para a ficha do veículo.
 */
class VehicleMaintenancePdfMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
     *
     * @var string
     */
    public $theme = 'revisalog';

    public function __construct(
        public Vehicle $vehicle,
        public string $pdfContent,
        public string $filename,
        /** @var list<array{filename: string, content: string, mime: string}> */
        public array $invoiceAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address(
                    (string) config('mail.reply_to.address'),
                    (string) config('mail.reply_to.name'),
                ),
            ],
            subject: "Histórico de manutenções — {$this->vehicleName()}",
        );
    }

    public function content(): Content
    {
        $maintenanceCount = $this->vehicle->maintenances()->count();
        $sealedCount = $this->vehicle->maintenances()->whereNotNull('verified_at')->count();

        return new Content(
            markdown: 'emails.vehicle-maintenance-pdf',
            with: [
                'vehicleName' => $this->vehicleName(),
                'plate' => (string) $this->vehicle->license_plate,
                'generatedAt' => DisplayTime::now()->format('d/m/Y').' às '.DisplayTime::now()->format('H:i'),
                'maintenanceCount' => $maintenanceCount,
                'provenanceSummary' => self::provenanceSummary($sealedCount, $maintenanceCount - $sealedCount),
                'attachmentLines' => $this->attachmentLines(),
                'vehicleUrl' => route('user.vehicles.show', $this->vehicle),
                'verificationUrl' => route('verification.lookup'),
            ],
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
            $content = $invoice['content'];
            if (! is_string($content) || $content === '') {
                continue;
            }

            $attachments[] = Attachment::fromData(fn () => $content, $invoice['filename'])
                ->withMime($invoice['mime']);
        }

        return $attachments;
    }

    /**
     * "3 com selo · 1 declarada": o mesmo contador compacto da capa do PDF.
     */
    public static function provenanceSummary(int $sealedCount, int $declaredCount): string
    {
        return number_format($sealedCount, 0, ',', '.').' com selo · '.VehicleMaintenanceHistory::declaredLabel($declaredCount);
    }

    /**
     * Uma linha por arquivo anexado, montada aqui para a pontuação não depender do Blade.
     *
     * @return list<string>
     */
    public function attachmentLines(): array
    {
        $lines = ["{$this->filename}: histórico de manutenções em PDF"];

        foreach ($this->invoiceAttachments as $invoice) {
            if (is_string($invoice['content'] ?? null) && $invoice['content'] !== '') {
                $lines[] = "{$invoice['filename']}: nota fiscal";
            }
        }

        return $lines;
    }

    private function vehicleName(): string
    {
        return trim("{$this->vehicle->brand} {$this->vehicle->model}");
    }
}
