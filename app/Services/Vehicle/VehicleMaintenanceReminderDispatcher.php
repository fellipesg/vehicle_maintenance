<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MaintenanceKmReminderNotification;
use App\Services\FcmService;
use Illuminate\Support\Collection;

class VehicleMaintenanceReminderDispatcher
{
    public function __construct(
        private readonly VehicleMaintenanceReminderService $reminders,
    ) {}

    public function dispatchDueReminders(): int
    {
        $sent = 0;

        User::query()
            ->whereIn('user_type', ['user', 'garage'])
            ->orderBy('id')
            ->chunkById(50, function (Collection $users) use (&$sent): void {
                foreach ($users as $user) {
                    $sent += $this->dispatchForUser($user);
                }
            });

        return $sent;
    }

    public function dispatchForUser(User $user): int
    {
        $sent = 0;

        $vehicles = $user->currentVehicles()
            ->with('maintenances')
            ->get();

        foreach ($vehicles as $vehicle) {
            if ($this->dispatchForVehicle($user, $vehicle)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function dispatchForVehicle(User $user, Vehicle $vehicle): bool
    {
        if (! $this->reminders->shouldNotify($vehicle)) {
            return false;
        }

        $summary = $this->reminders->summarize($vehicle);
        $nextDue = $summary['next_due_kilometers'];

        if ($nextDue === null) {
            return false;
        }

        if ($this->alreadyNotified($user, $vehicle->id, $nextDue)) {
            return false;
        }

        $user->notify(new MaintenanceKmReminderNotification($vehicle, $summary));

        $this->sendPushNotification($user, $vehicle, $summary);

        return true;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function sendPushNotification(User $user, Vehicle $vehicle, array $summary): void
    {
        try {
            $payload = (new MaintenanceKmReminderNotification($vehicle, $summary))->toArray($user);

            app(FcmService::class)->sendToUser(
                $user->id,
                (string) ($payload['title'] ?? 'Lembrete de revisão'),
                (string) ($payload['body'] ?? 'Seu veículo está próximo da revisão programada.'),
                [
                    'type' => 'maintenance-km-reminder',
                    'vehicle_id' => (string) $vehicle->id,
                    'next_due_kilometers' => (string) ($summary['next_due_kilometers'] ?? ''),
                    'kilometers_remaining' => (string) ($summary['kilometers_remaining'] ?? ''),
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function alreadyNotified(User $user, int $vehicleId, int $nextDueKilometers): bool
    {
        $query = $user->notifications()
            ->where('type', MaintenanceKmReminderNotification::class);

        if ($user->getConnection()->getDriverName() === 'pgsql') {
            return $query
                ->whereRaw("(data::jsonb->>'vehicle_id')::bigint = ?", [$vehicleId])
                ->whereRaw("(data::jsonb->>'next_due_kilometers')::bigint = ?", [$nextDueKilometers])
                ->exists();
        }

        return $query
            ->where('data->vehicle_id', $vehicleId)
            ->where('data->next_due_kilometers', $nextDueKilometers)
            ->exists();
    }
}
