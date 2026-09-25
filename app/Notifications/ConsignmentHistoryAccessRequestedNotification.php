<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VehicleConsignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The garage asking the owner directly for the pre-existing history — one click for the
 * owner, and consent from the person the history actually belongs to.
 */
class ConsignmentHistoryAccessRequestedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("{$garageName} quer ver o histórico do seu {$label}")
            ->greeting('Olá, '.$this->firstName().'!')
            ->line("A garagem **{$garageName}**, que está com o seu **{$label}** (placa {$vehicle->license_plate}) para venda, pediu acesso ao histórico de manutenções anterior à consignação.")
            ->line('Esse histórico é seu e hoje ela não vê nada dele. Liberar costuma ajudar na venda, porque mostra ao comprador o cuidado que o carro teve.')
            ->action('Ver e decidir', route('consignments.owner.show', $this->consignment->owner_action_token))
            ->line('Você pode recusar simplesmente ignorando este e-mail.')
            ->salutation('Revisalog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $vehicle = $this->consignment->vehicle;

        return [
            'type' => 'consignment-history-request',
            'title' => 'Pedido de acesso ao histórico',
            'body' => "{$this->consignment->garageUser->name} pediu acesso ao histórico do seu {$vehicle->brand} {$vehicle->model}.",
            'consignment_id' => $this->consignment->id,
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'garage_name' => $this->consignment->garageUser->name,
            'action_url' => route('consignments.owner.show', $this->consignment->owner_action_token, absolute: false),
        ];
    }

    private function firstName(): string
    {
        $name = trim($this->consignment->ownerUser?->name ?? $this->consignment->owner_name);
        $first = trim(explode(' ', $name, 2)[0] ?? '');

        return $first !== '' ? $first : 'motorista';
    }
}
