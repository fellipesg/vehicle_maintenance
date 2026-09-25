<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VehicleConsignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that a garage declared their vehicle on consignment, so nothing is
 * recorded against their car without them knowing.
 */
class VehicleConsignmentStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VehicleConsignment $consignment)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicle = $this->consignment->vehicle;
        $garageName = $this->consignment->garageUser->name;
        $label = trim("{$vehicle->brand} {$vehicle->model}");

        $message = (new MailMessage)
            ->subject("{$garageName} registrou seu {$label} em consignação")
            ->greeting('Olá, '.$this->firstName().'!')
            ->line("A garagem **{$garageName}** informou que está com o seu **{$label}** (placa {$vehicle->license_plate}) para venda em consignação.")
            ->line('A partir de agora ela pode registrar no Revisalog as manutenções que fizer no veículo, e você recebe um aviso a cada registro.')
            ->line('O histórico de manutenções que o veículo já tinha continua privado. A garagem só vê esse histórico se você liberar.')
            ->action('Ver e responder', $this->actionUrl());

        if ($this->consignment->isHistoryReviewPending()) {
            $message->line('A garagem também anexou uma procuração pedindo acesso ao histórico anterior. Você pode liberar direto pelo botão acima, sem esperar a nossa análise.');
        }

        return $message
            ->line('Se você não autorizou essa garagem, use o mesmo link para contestar.')
            ->salutation('Revisalog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $vehicle = $this->consignment->vehicle;

        return [
            'type' => 'consignment-started',
            'title' => 'Veículo registrado em consignação',
            'body' => "{$this->consignment->garageUser->name} informou que está com o seu {$vehicle->brand} {$vehicle->model} para venda.",
            'consignment_id' => $this->consignment->id,
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'garage_name' => $this->consignment->garageUser->name,
            'action_url' => $this->actionUrl(absolute: false),
        ];
    }

    private function actionUrl(bool $absolute = true): string
    {
        return route('consignments.owner.show', $this->consignment->owner_action_token, absolute: $absolute);
    }

    private function firstName(): string
    {
        $name = trim($this->consignment->ownerUser?->name ?? $this->consignment->owner_name);
        $first = trim(explode(' ', $name, 2)[0] ?? '');

        return $first !== '' ? $first : 'motorista';
    }
}
