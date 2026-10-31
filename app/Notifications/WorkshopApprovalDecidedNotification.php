<?php

namespace App\Notifications;

use App\Enums\WorkshopStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Resposta da análise do CADASTRO da oficina: aprovada (a área da oficina abre inteira e o Selo
 * volta a sair) ou recusada (segue só "Minha oficina", com o motivo).
 *
 * Não confundir com WorkshopReviewDecidedNotification, que é a resposta da oficina sobre uma
 * manutenção que alguém declarou citando ela.
 */
class WorkshopApprovalDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $workshopName,
        public WorkshopStatus $status,
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

        if ($this->status === WorkshopStatus::Rejected && filled($this->note)) {
            $message->line('Motivo: '.$this->note);
        }

        return $message
            ->action($this->status === WorkshopStatus::Approved ? 'Abrir a área da oficina' : 'Ver o cadastro da oficina', route(
                $this->status === WorkshopStatus::Approved ? 'workshop.dashboard' : 'workshop.profile.show',
            ))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-approval-decided',
            'status' => $this->status->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'workshop_name' => $this->workshopName,
        ];
    }

    public function title(): string
    {
        return $this->status === WorkshopStatus::Approved
            ? 'Sua oficina foi aprovada'
            : 'Não conseguimos aprovar sua oficina';
    }

    public function body(): string
    {
        return $this->status === WorkshopStatus::Approved
            ? "O cadastro da {$this->workshopName} foi conferido. As ordens de serviço, o Selo da oficina e os convites ao cliente já estão liberados."
            : "Não conseguimos confirmar o cadastro da {$this->workshopName}. Por enquanto só a tela \"Minha oficina\" fica disponível: corrija os dados por lá ou fale com a equipe.";
    }
}
