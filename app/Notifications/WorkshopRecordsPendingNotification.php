<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Avisa o proprietário que chegou a um veículo com registros de oficina feitos antes da conta dele.
 * Nada é vinculado sozinho: ele escolhe em "Registros de oficinas" (MaintenanceOwnerDecisionService).
 */
class WorkshopRecordsPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'workshop_records_pending';

    public function __construct(public Vehicle $vehicle, public int $count)
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
            ->replyTo((string) config('mail.reply_to.address'), (string) config('mail.reply_to.name'))
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line($this->body())
            ->line('Nada entra no seu histórico sem a sua escolha: você decide se vincula, se aceita as notas e fotos e se oculta da consulta pública.')
            ->action('Ver registros de oficinas', route('user.workshop-records.index'))
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'title' => $this->title(),
            'body' => $this->body(),
            'vehicle_id' => $this->vehicle->id,
            'count' => $this->count,
            'action_url' => route('user.workshop-records.index', absolute: false),
            'action_label' => 'Ver registros',
        ];
    }

    public function title(): string
    {
        return 'Registros de oficina aguardam a sua decisão';
    }

    public function body(): string
    {
        $label = trim("{$this->vehicle->brand} {$this->vehicle->model}");

        return $this->count === 1
            ? "1 registro de oficina aguarda a sua decisão no seu {$label}."
            : "{$this->count} registros de oficina aguardam a sua decisão no seu {$label}.";
    }
}
