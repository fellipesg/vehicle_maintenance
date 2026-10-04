<?php

namespace App\Notifications;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Resposta da oficina para quem declarou a manutenção: confirmada (agora com o Selo da oficina) ou
 * não reconhecida (o vínculo com a oficina saiu).
 */
class WorkshopReviewDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
        public WorkshopReviewStatus $status,
        public string $workshopName,
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

        if ($this->status === WorkshopReviewStatus::Rejected && filled($this->note)) {
            $message->line('Motivo informado pela oficina: '.$this->note);
        }

        return $message
            ->action('Ver manutenção', $this->maintenanceUrl($notifiable))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-review-decided',
            'status' => $this->status->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'maintenance_id' => $this->maintenance->id,
            'vehicle_id' => $this->maintenance->vehicle_id,
            'workshop_name' => $this->workshopName,
        ];
    }

    public function title(): string
    {
        return $this->status === WorkshopReviewStatus::Confirmed
            ? "{$this->workshopName} confirmou o seu serviço"
            : "{$this->workshopName} não reconheceu um serviço";
    }

    public function body(): string
    {
        $service = $this->maintenance->maintenance_type;
        $date = $this->maintenance->maintenance_date?->format('d/m/Y');
        $serviceLabel = $date ? "\"{$service}\" de {$date}" : "\"{$service}\"";

        return $this->status === WorkshopReviewStatus::Confirmed
            ? "A manutenção {$serviceLabel} agora tem o Selo da {$this->workshopName}. Ela passa a ser da oficina: correções são feitas por ela."
            : "A {$this->workshopName} informou que a manutenção {$serviceLabel} não foi feita lá. O registro continua no histórico como declarado por você, sem vínculo com a oficina.";
    }

    private function maintenanceUrl(object $notifiable): string
    {
        $routeName = ($notifiable instanceof User && $notifiable->isGarage())
            ? 'garage.maintenances.show'
            : 'user.maintenances.show';

        return route($routeName, $this->maintenance);
    }
}
