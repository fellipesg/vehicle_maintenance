<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso para quem era o dono atual quando a equipe encerra o vínculo dele com o veículo
 * (App\Services\Vehicle\VehicleOwnershipEndingService). O motivo que o admin escreveu fica no
 * registro interno e não vai no e-mail; quem discorda responde o e-mail para a equipe.
 */
class VehicleOwnershipEndedByTeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Vehicle $vehicle)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicleName = trim("{$this->vehicle->brand} {$this->vehicle->model}");
        $plate = (string) $this->vehicle->license_plate;
        $vehicleLabel = $plate !== '' ? "{$vehicleName} (placa {$plate})" : $vehicleName;
        $firstName = Str::before(trim((string) ($notifiable->name ?? '')), ' ');

        return (new MailMessage)
            ->subject("{$vehicleName} não está mais vinculado à sua conta na RevisaLog")
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line("A equipe RevisaLog encerrou o vínculo do {$vehicleLabel} com a sua conta. Ele não aparece mais como seu.")
            ->line('O histórico de manutenções continua no chassi do veículo.')
            ->line('Se você ainda é o dono do veículo, responda este e-mail para a equipe conferir.')
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vehicle-ownership-ended-by-team',
            'vehicle_id' => $this->vehicle->id,
        ];
    }
}
