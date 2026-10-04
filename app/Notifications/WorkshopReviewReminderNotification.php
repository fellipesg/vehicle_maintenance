<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Lembrete para a conta da oficina: serviços declarados por clientes que esperam validação há
 * WorkshopReviewReminder::AFTER_DAYS dias ou mais. Um aviso por oficina, com o total pendente.
 */
class WorkshopReviewReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $staleCount,
        public int $pendingCount,
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

        return (new MailMessage)
            ->subject($this->title())
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line($this->body())
            ->line('Cada serviço confirmado ganha o Selo da oficina com a sua logo, e o cliente passa a receber os lembretes que você configurar.')
            ->action('Ver validações', route('workshop.reviews.index'))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-review-reminder',
            'title' => $this->title(),
            'body' => $this->body(),
            'pending_count' => $this->pendingCount,
            'action_url' => route('workshop.reviews.index', absolute: false),
            'action_label' => 'Ver validações',
        ];
    }

    public function title(): string
    {
        return $this->pendingCount === 1
            ? '1 serviço aguarda a sua validação'
            : "{$this->pendingCount} serviços aguardam a sua validação";
    }

    public function body(): string
    {
        $stale = $this->staleCount === 1
            ? '1 deles espera'
            : "{$this->staleCount} deles esperam";

        return "Clientes registraram serviços citando a sua oficina e {$stale} há mais de uma semana. Confirme os que foram feitos aí ou marque \"Não reconheço\".";
    }
}
