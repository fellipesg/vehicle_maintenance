<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link de confirmação da exclusão de conta pedida em /excluir-conta.
 *
 * O e-mail é a prova de posse: só quem abre a caixa do endereço cadastrado consegue concluir.
 * Por isso o link é assinado, expira e nunca sai daqui para outro endereço que não o da conta.
 *
 * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
 */
class AccountDeletionRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @var string
     */
    public $theme = 'revisalog';

    public function __construct(
        public string $confirmationUrl,
        public int $expiresInMinutes,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@revisalog.com.br', 'RevisaLog'),
            replyTo: [new Address((string) config('mail.reply_to.address'), 'RevisaLog')],
            subject: 'Confirme a exclusão da sua conta RevisaLog',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account-deletion',
            with: [
                'confirmationUrl' => $this->confirmationUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
                'supportEmail' => (string) config('legal.support_email'),
            ],
        );
    }
}
