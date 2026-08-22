<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceKmReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *     interval_kilometers: int,
     *     anchor_kilometers: int|null,
     *     next_due_kilometers: int|null,
     *     kilometers_remaining: int|null,
     *     is_overdue: bool,
     *     progress_percent: float|null,
     *     notify_before_kilometers: int
     * }  $summary
     */
    public function __construct(
        public Vehicle $vehicle,
        public array $summary,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vehicleLabel = trim("{$this->vehicle->brand} {$this->vehicle->model}");
        $plate = $this->vehicle->license_plate;
        $isOverdue = (bool) ($this->summary['is_overdue'] ?? false);
        $remaining = (int) ($this->summary['kilometers_remaining'] ?? 0);
        $nextDue = (int) ($this->summary['next_due_kilometers'] ?? 0);
        $ownerName = $this->ownerFirstName($notifiable);

        if ($isOverdue) {
            $subject = "Revisão em atraso — {$vehicleLabel}";
            $intro = "O veículo **{$vehicleLabel}** (placa {$plate}) passou da quilometragem estimada para a próxima revisão preventiva.";
        } else {
            $formattedRemaining = number_format($remaining, 0, ',', '.');
            $subject = "Faltam {$formattedRemaining} km para revisão — {$vehicleLabel}";
            $intro = "Faltam **{$formattedRemaining} km** para a próxima revisão estimada do **{$vehicleLabel}** (placa {$plate}).";
        }

        $formattedNextDue = number_format($nextDue, 0, ',', '.');

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Olá, {$ownerName}!")
            ->line($intro)
            ->line("Próxima revisão estimada: **{$formattedNextDue} km**.")
            ->action('Ver veículo', $this->vehicleUrl($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isOverdue = (bool) ($this->summary['is_overdue'] ?? false);
        $remaining = (int) ($this->summary['kilometers_remaining'] ?? 0);
        $vehicleLabel = trim("{$this->vehicle->brand} {$this->vehicle->model}");

        if ($isOverdue) {
            $title = "Revisão em atraso — {$vehicleLabel}";
            $body = "O veículo {$vehicleLabel} ({$this->vehicle->license_plate}) passou da quilometragem estimada para a próxima revisão.";
        } else {
            $formattedRemaining = number_format($remaining, 0, ',', '.');
            $title = "Faltam {$formattedRemaining} km para revisão";
            $body = "Próxima revisão estimada do {$vehicleLabel} ({$this->vehicle->license_plate}).";
        }

        return [
            'type' => 'maintenance-km-reminder',
            'title' => $title,
            'body' => $body,
            'vehicle_id' => $this->vehicle->id,
            'license_plate' => $this->vehicle->license_plate,
            'brand' => $this->vehicle->brand,
            'model' => $this->vehicle->model,
            'next_due_kilometers' => $this->summary['next_due_kilometers'],
            'kilometers_remaining' => $this->summary['kilometers_remaining'],
            'is_overdue' => $isOverdue,
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
