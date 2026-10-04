<?php

namespace App\Mail;

use App\Models\WorkshopProspect;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\Mime\Address as SymfonyAddress;
use Symfony\Component\Mime\Email;

/**
 * Convite a uma oficina prospectada (primeiro contato ou follow-up). Propositalmente simples: sem o tema
 * de Markdown da marca, sem imagens e sem rastreio de abertura. Ver .ai/rules/outreach.md.
 *
 * O Reply-To substitui o global (mail.reply_to → suporte@): a resposta da oficina vai só para a caixa de outreach.
 *
 * Não é ShouldQueue: o job SendWorkshopProspectInvite já é a fila e envia pelo mailer de outreach.
 */
class WorkshopProspectInviteMail extends Mailable
{
    public const FIRST_TOUCH = 'first_touch';

    public const FOLLOW_UP = 'follow_up';

    public function __construct(
        public WorkshopProspect $prospect,
        public string $variant = self::FIRST_TOUCH,
        public ?string $messageId = null,
    ) {
        $this->withSymfonyMessage(function (Email $message): void {
            $message->replyTo(new SymfonyAddress($this->replyToAddress()));
        });
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('outreach.from.address');
        if (blank($fromAddress)) {
            throw new RuntimeException('OUTREACH_FROM_ADDRESS não está configurado.');
        }

        $name = $this->prospect->displayName();

        return new Envelope(
            from: new Address($fromAddress, (string) config('outreach.from.name')),
            replyTo: [new Address($this->replyToAddress())],
            subject: $this->isThreadedFollowUp() ? "Re: {$name} no RevisaLog" : "{$name} no RevisaLog",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.outreach.invite',
            text: 'emails.outreach.invite-text',
            with: [
                'nome' => $this->prospect->displayName(),
                'assinatura' => (string) config('outreach.sender_signature_name'),
                'link' => $this->clickUrl(),
                'sair' => $this->unsubscribeUrl(),
                'followUp' => $this->variant === self::FOLLOW_UP,
            ],
        );
    }

    public function headers(): Headers
    {
        $threadId = $this->isThreadedFollowUp() ? $this->prospect->first_message_id : null;

        return new Headers(
            messageId: $this->variant === self::FIRST_TOUCH ? $this->messageId : null,
            references: $threadId === null ? [] : [$threadId],
            text: [
                ...($threadId === null ? [] : ['In-Reply-To' => '<'.$threadId.'>']),
                'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>, <mailto:'.$this->replyToAddress().'?subject=descadastrar>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /**
     * O follow-up só vira resposta ("Re:") se o primeiro envio guardou o Message-ID; sem ele (linha antiga)
     * não finge uma conversa que não existe.
     */
    private function isThreadedFollowUp(): bool
    {
        return $this->variant === self::FOLLOW_UP && filled($this->prospect->first_message_id);
    }

    public function clickUrl(): string
    {
        return route('outreach.click', ['token' => $this->prospect->token]);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('outreach.unsubscribe.show', ['token' => $this->prospect->token]);
    }

    private function replyToAddress(): string
    {
        return (string) (config('outreach.reply_to') ?: config('outreach.from.address'));
    }
}
