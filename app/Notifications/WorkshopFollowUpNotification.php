<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkshopFollowUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public WorkshopMessageTemplate $template,
        public Vehicle $vehicle,
        public string $renderedTitle,
        public string $renderedBody,
        public array $context,
        public ?int $nextDueKilometers = null,
        public ?int $sourceMaintenanceId = null,
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
        $ownerName = $this->ownerFirstName($notifiable);

        return (new MailMessage)
            ->subject($this->renderedTitle)
            ->greeting("Olá, {$ownerName}!")
            ->line($this->renderedBody)
            ->action('Ver veículo', $this->vehicleUrl($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workshop-follow-up',
            'title' => $this->renderedTitle,
            'body' => $this->renderedBody,
            'template_id' => $this->template->id,
            'workshop_id' => $this->template->workshop_id,
            'workshop_name' => $this->context['workshop_name'] ?? $this->template->workshop->name,
            'trigger' => $this->template->trigger->value,
            'vehicle_id' => $this->vehicle->id,
            'license_plate' => $this->vehicle->license_plate,
            'brand' => $this->vehicle->brand,
            'model' => $this->vehicle->model,
            'estimated_km' => $this->context['estimated_km'] ?? null,
            'next_due_kilometers' => $this->nextDueKilometers,
            'source_maintenance_id' => $this->sourceMaintenanceId,
            'vehicle_url' => $this->vehicleUrl($notifiable, absolute: false),
        ];
    }

    private function vehicleUrl(object $notifiable, bool $absolute = true): string
    {
        $routeName = ($notifiable instanceof User && $notifiable->isGarage())
            ? 'garage.vehicles.show'
            : 'user.vehicles.show';

        return route($routeName, $this->vehicle, absolute: $absolute);
    }

    private function ownerFirstName(object $notifiable): string
    {
        if (! $notifiable instanceof User) {
            return 'motorista';
        }

        $firstName = trim(explode(' ', trim($notifiable->name), 2)[0] ?? '');

        return $firstName !== '' ? $firstName : 'motorista';
    }
}
