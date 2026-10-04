<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso para a conta da oficina quando um cliente declara um serviço citando a oficina
 * (App\Services\Maintenance\WorkshopReviewService::requestReview). Leva para a fila "Validações".
 */
class WorkshopReviewRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Maintenance $maintenance)
    {
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
            ->line('Ao confirmar, o serviço recebe o Selo da oficina com a logo da sua oficina e o cliente passa a receber os lembretes que você configurar. Se o serviço não foi feito aí, marque "Não reconheço".')
            ->action('Validar serviço', route('workshop.reviews.index'))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-review-requested',
            'title' => $this->title(),
            'body' => $this->body(),
            'maintenance_id' => $this->maintenance->id,
            'action_url' => route('workshop.reviews.index', absolute: false),
            'action_label' => 'Validar serviço',
        ];
    }

    public function title(): string
    {
        return 'Um cliente registrou um serviço na sua oficina';
    }

    public function body(): string
    {
        $vehicle = $this->maintenance->vehicle;
        $vehicleName = $vehicle ? trim("{$vehicle->brand} {$vehicle->model}") : 'Veículo';
        $plate = $vehicle?->license_plate ? " (placa {$vehicle->license_plate})" : '';
        $date = $this->maintenance->maintenance_date?->format('d/m/Y');

        return "{$vehicleName}{$plate}: {$this->maintenance->maintenance_type}".($date ? " em {$date}" : '').'. Confirme se o serviço foi feito na sua oficina.';
    }
}
