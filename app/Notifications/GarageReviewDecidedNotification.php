<?php

namespace App\Notifications;

use App\Enums\GarageStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Resposta da análise do cadastro da loja: aprovada (a área do lojista abre inteira) ou recusada
 * (segue só "Minha loja", com o motivo).
 */
class GarageReviewDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $garageName,
        public GarageStatus $status,
        public ?string $note = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = Str::before(trim((string) ($notifiable->name ?? '')), ' ');

        $message = (new MailMessage)
            ->subject($this->title())
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line($this->body());

        if ($this->status === GarageStatus::Rejected && filled($this->note)) {
            $message->line('Motivo: '.$this->note);
        }

        return $message
            ->action($this->status === GarageStatus::Approved ? 'Abrir a área do lojista' : 'Ver o cadastro da loja', route(
                $this->status === GarageStatus::Approved ? 'garage.dashboard' : 'garage.profile.show',
            ))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'garage-review-decided',
            'status' => $this->status->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'garage_name' => $this->garageName,
        ];
    }

    public function title(): string
    {
        return $this->status === GarageStatus::Approved
            ? 'Sua loja foi aprovada'
            : 'Não conseguimos aprovar sua loja';
    }

    public function body(): string
    {
        return $this->status === GarageStatus::Approved
            ? "O cadastro da {$this->garageName} foi conferido. O estoque, as manutenções e a consignação já estão liberados."
            : "Não conseguimos confirmar o cadastro da {$this->garageName}. Por enquanto só a tela \"Minha loja\" fica disponível: corrija os dados por lá ou fale com a equipe.";
    }
}
