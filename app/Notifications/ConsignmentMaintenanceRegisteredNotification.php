<?php

namespace App\Notifications;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\VehicleConsignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every maintenance a consigning garage records reaches the owner, which is what makes
 * the declaration trustworthy without a power of attorney.
 */
class ConsignmentMaintenanceRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VehicleConsignment $consignment,
        public Maintenance $maintenance,
    ) {
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
            ->subject("Nova manutenção registrada no seu {$label}")
            ->greeting('Olá, '.$this->firstName().'!')
            ->line("A garagem **{$garageName}** registrou uma manutenção no seu **{$label}** (placa {$vehicle->license_plate}).")
            ->line("**{$this->maintenance->maintenance_type}** — {$this->maintenance->maintenance_date->format('d/m/Y')} · {$this->formattedKilometers()} km")
            ->line('Esse registro entra no histórico do veículo e valoriza o carro na hora da venda.')
            ->action('Ver registro', $this->actionUrl())
            ->line('Se esse serviço não foi feito, use o mesmo link para contestar.')
            ->salutation('Revisalog');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $vehicle = $this->consignment->vehicle;

        return [
            'type' => 'consignment-maintenance',
            'title' => 'Nova manutenção registrada pela garagem',
            'body' => "{$this->consignment->garageUser->name} registrou {$this->maintenance->maintenance_type} no seu {$vehicle->brand} {$vehicle->model}.",
            'consignment_id' => $this->consignment->id,
            'maintenance_id' => $this->maintenance->id,
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

    private function formattedKilometers(): string
    {
        return number_format((int) $this->maintenance->kilometers, 0, ',', '.');
    }

    private function firstName(): string
    {
        $name = trim($this->consignment->ownerUser?->name ?? $this->consignment->owner_name);
        $first = trim(explode(' ', $name, 2)[0] ?? '');

        return $first !== '' ? $first : 'motorista';
    }
}
