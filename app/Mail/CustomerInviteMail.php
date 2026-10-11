<?php

namespace App\Mail;

use App\Models\Maintenance;
use App\Models\MaintenanceInvite;
use App\Support\Maintenance\CustomerInviteMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Convite do cliente de um carro sem proprietário. Sai pelo mailer padrão (transacional), na fila,
 * uma vez por OS. Sem placa, chassi ou valores. O link de descadastro é assinado e carrega o
 * endereço criptografado, para gravá-lo em email_suppressions sem guardá-lo no banco.
 */
class CustomerInviteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @var string
     */
    public $theme = 'revisalog';

    public function __construct(
        public Maintenance $maintenance,
        public MaintenanceInvite $invite,
        public string $recipient,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $message = $this->message();

        return new Envelope(
            from: new Address('noreply@revisalog.com.br', 'RevisaLog'),
            replyTo: [new Address((string) config('mail.reply_to.address'), 'RevisaLog')],
            subject: $message->workshopName().' registrou um serviço no seu '.$message->vehicleLabel(),
        );
    }

    public function content(): Content
    {
        $message = $this->message();

        return new Content(
            markdown: 'emails.customer-invite',
            with: [
                'workshopName' => $message->workshopName(),
                'vehicleLabel' => $message->vehicleLabel(),
                'serviceDate' => $message->serviceDate(),
                'inviteUrl' => $message->inviteUrl(),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('invites.unsubscribe.show', [
            'payload' => Crypt::encryptString(mb_strtolower(trim($this->recipient))),
        ]);
    }

    private function message(): CustomerInviteMessage
    {
        return new CustomerInviteMessage($this->maintenance, $this->invite);
    }
}
