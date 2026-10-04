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

/**
 * Convite a uma oficina prospectada (primeiro contato ou follow-up). Propositalmente simples: sem o tema
 * de Markdown da marca, sem imagens e sem rastreio de abertura. Ver .ai/rules/outreach.md.
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
    ) {}

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
            subject: $this->variant === self::FOLLOW_UP ? "Re: {$name} no RevisaLog" : "{$name} no RevisaLog",
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
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>, <mailto:'.$this->replyToAddress().'?subject=descadastrar>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
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
