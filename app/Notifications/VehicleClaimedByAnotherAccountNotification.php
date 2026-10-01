<?php

namespace App\Notifications;

use App\Models\Vehicle;
use App\Support\DisplayTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso para quem era o dono atual quando outra conta vincula o veículo com o CRLV-e
 * (App\Services\Vehicle\VehicleOwnershipService::claimExisting). O leitor do CRLV-e confere só o
 * texto do PDF, então é por aqui que o dono fica sabendo de um vínculo que não fez: se não vendeu o
 * veículo, responde o e-mail para a equipe conferir. A outra conta não é identificada no aviso.
 */
class VehicleClaimedByAnotherAccountNotification extends Notification implements ShouldQueue
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
        $claimedAt = DisplayTime::now();

        return (new MailMessage)
            ->subject("{$vehicleName} foi vinculado a outra conta na RevisaLog")
            ->replyTo(
                (string) config('mail.reply_to.address'),
                (string) config('mail.reply_to.name'),
            )
            ->greeting($firstName !== '' ? "Olá, {$firstName}!" : 'Olá!')
            ->line("O {$vehicleLabel} foi vinculado a outra conta na RevisaLog com o CRLV-e do veículo, em {$claimedAt->format('d/m/Y')} às {$claimedAt->format('H:i')}. Ele não aparece mais como seu na sua conta.")
            ->line('Se você vendeu o veículo, não precisa fazer nada: o histórico de manutenções continua no chassi.')
            ->line('Se você não vendeu, responda este e-mail para a equipe RevisaLog conferir o documento.')
            ->salutation('RevisaLog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vehicle-claimed-by-another-account',
            'vehicle_id' => $this->vehicle->id,
        ];
    }
}
