<?php

namespace App\Services\Workshop;

use App\Enums\WorkshopMessageTrigger;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopMessageDispatch;
use App\Models\WorkshopMessageTemplate;
use App\Notifications\WorkshopFollowUpNotification;
use App\Services\FcmService;
use App\Services\Vehicle\VehicleEstimatedKmService;
use App\Services\Vehicle\VehicleMaintenanceReminderService;
use App\Support\WorkshopMessageTemplateRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkshopMessageTemplateDispatcher
{
    /**
     * Veículos (ou OS) por lote na rodada diária.
     */
    private const CHUNK = 100;

    public function __construct(
        private readonly VehicleEstimatedKmService $estimatedKm,
        private readonly VehicleMaintenanceReminderService $reminders,
        private readonly WorkshopMessageTemplateRenderer $renderer,
    ) {}

    public function dispatchFollowUps(): int
    {
        $sent = 0;

        WorkshopMessageTemplate::query()
            ->where('is_active', true)
            ->with('workshop')
            ->orderBy('id')
            ->chunkById(50, function (Collection $templates) use (&$sent): void {
                foreach ($templates as $template) {
                    $sent += match ($template->trigger) {
                        WorkshopMessageTrigger::ScheduledRevision => $this->dispatchScheduledTemplate($template),
                        WorkshopMessageTrigger::CorrectiveFollowUp => $this->dispatchCorrectiveTemplate($template),
                    };
                }
            });

        return $sent;
    }

    private function dispatchScheduledTemplate(WorkshopMessageTemplate $template): int
    {
        $sent = 0;
        $vehicleIds = Maintenance::query()
            ->where('workshop_id', $template->workshop_id)
            ->whereNotNull('workshop_id')
            ->distinct()
            ->pluck('vehicle_id');

        if ($vehicleIds->isEmpty()) {
            return 0;
        }

        $leadKm = $template->lead_kilometers
            ?: (int) config('maintenance_intervals.notify_before_kilometers', 2_000);

        Vehicle::query()
            ->with('maintenances')
            ->whereIn('id', $vehicleIds)
            ->chunkById(self::CHUNK, function (Collection $vehicles) use ($template, $leadKm, &$sent): void {
                $ownersByVehicle = $this->currentOwnersByVehicle(new Collection($vehicles->modelKeys()));

                foreach ($vehicles as $vehicle) {
                    $estimatedKm = $this->estimatedKm->estimate($vehicle);
                    $summary = $this->reminders->summarizeAtKilometers($vehicle, $estimatedKm);
                    $nextDue = $summary['next_due_kilometers'];

                    if ($nextDue === null) {
                        continue;
                    }

                    if (($summary['kilometers_remaining'] ?? PHP_INT_MAX) > $leadKm) {
                        continue;
                    }

                    foreach ($ownersByVehicle[$vehicle->id] ?? [] as $owner) {
                        if ($this->dispatchToOwner(
                            $template,
                            $owner,
                            $vehicle,
                            (string) $nextDue,
                            $estimatedKm,
                            $nextDue,
                        )) {
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    private function dispatchCorrectiveTemplate(WorkshopMessageTemplate $template): int
    {
        $sent = 0;
        $minDays = $template->min_days_since_service ?: 60;
        $cutoffDate = now()->subDays($minDays)->startOfDay();

        $maintenancesQuery = Maintenance::query()
            ->where('workshop_id', $template->workshop_id)
            ->whereNotNull('workshop_id')
            ->whereDate('maintenance_date', '<=', $cutoffDate);

        if ($template->service_category !== null) {
            $maintenancesQuery->where('service_category', $template->service_category);
        }

        $maintenancesQuery
            ->with('vehicle.maintenances')
            ->chunkById(self::CHUNK, function (Collection $maintenances) use ($template, &$sent): void {
                $ownersByVehicle = $this->currentOwnersByVehicle(
                    $maintenances->pluck('vehicle_id')->filter()->unique()->values(),
                );

                foreach ($maintenances as $maintenance) {
                    $vehicle = $maintenance->vehicle;

                    if ($vehicle === null) {
                        continue;
                    }

                    $estimatedKm = $this->estimatedKm->estimate($vehicle);

                    foreach ($ownersByVehicle[$vehicle->id] ?? [] as $owner) {
                        if ($this->dispatchToOwner(
                            $template,
                            $owner,
                            $vehicle,
                            (string) $maintenance->id,
                            $estimatedKm,
                            null,
                            $maintenance,
                        )) {
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    /**
     * Donos atuais de um lote de veículos, em duas consultas: a rodada diária fazia uma por veículo
     * por template. O filtro é o mesmo de User::currentVehicles() — que não pode ser carregado em
     * lote, porque lê o tenant_id de uma conta só.
     *
     * @param  Collection<int, int|string>  $vehicleIds
     * @return array<int, list<User>>
     */
    private function currentOwnersByVehicle(Collection $vehicleIds): array
    {
        if ($vehicleIds->isEmpty()) {
            return [];
        }

        // Postgres rejects "boolean = 1"; use a SQL boolean literal.
        $links = DB::table('user_vehicles')
            ->join('users', 'users.id', '=', 'user_vehicles.user_id')
            ->whereIn('user_vehicles.vehicle_id', $vehicleIds)
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->whereIn('users.user_type', ['user', 'garage'])
            ->whereRaw('(users.tenant_id is null or users.tenant_id = user_vehicles.tenant_id)')
            ->orderBy('users.id')
            ->get(['user_vehicles.vehicle_id', 'user_vehicles.user_id']);

        if ($links->isEmpty()) {
            return [];
        }

        $owners = User::query()
            ->whereIn('id', $links->pluck('user_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $byVehicle = [];

        foreach ($links as $link) {
            $owner = $owners->get((int) $link->user_id);

            if ($owner !== null) {
                // A chave do dono evita repetir a conta que tem duas linhas para o mesmo veículo.
                $byVehicle[(int) $link->vehicle_id][(int) $link->user_id] = $owner;
            }
        }

        return array_map(array_values(...), $byVehicle);
    }

    private function dispatchToOwner(
        WorkshopMessageTemplate $template,
        User $owner,
        Vehicle $vehicle,
        string $dedupeKey,
        int $estimatedKm,
        ?int $nextDueKm = null,
        ?Maintenance $sourceMaintenance = null,
    ): bool {
        if ($this->alreadyDispatched($template, $owner, $vehicle, $dedupeKey)) {
            return false;
        }

        $context = $this->renderer->buildContext(
            $owner,
            $vehicle,
            $template->workshop,
            $estimatedKm,
            $nextDueKm,
            $sourceMaintenance,
        );

        $title = $this->renderer->render($template->title, $context);
        $body = $this->renderer->render($template->body, $context);

        DB::transaction(function () use ($template, $owner, $vehicle, $dedupeKey, $title, $body, $context, $nextDueKm, $sourceMaintenance): void {
            WorkshopMessageDispatch::query()->create([
                'workshop_id' => $template->workshop_id,
                'user_id' => $owner->id,
                'vehicle_id' => $vehicle->id,
                'template_id' => $template->id,
                'dedupe_key' => $dedupeKey,
                'sent_at' => now(),
            ]);

            $owner->notify(new WorkshopFollowUpNotification(
                $template,
                $vehicle,
                $title,
                $body,
                $context,
                $nextDueKm,
                $sourceMaintenance?->id,
            ));
        });

        $this->sendPushNotification($owner, $template, $vehicle, $title, $body, $nextDueKm, $sourceMaintenance?->id);

        return true;
    }

    private function alreadyDispatched(
        WorkshopMessageTemplate $template,
        User $owner,
        Vehicle $vehicle,
        string $dedupeKey,
    ): bool {
        return WorkshopMessageDispatch::query()
            ->where('template_id', $template->id)
            ->where('user_id', $owner->id)
            ->where('vehicle_id', $vehicle->id)
            ->where('dedupe_key', $dedupeKey)
            ->exists();
    }

    private function sendPushNotification(
        User $owner,
        WorkshopMessageTemplate $template,
        Vehicle $vehicle,
        string $title,
        string $body,
        ?int $nextDueKm,
        ?int $sourceMaintenanceId,
    ): void {
        try {
            app(FcmService::class)->sendToUser(
                $owner->id,
                $title,
                $body,
                [
                    'type' => 'workshop-follow-up',
                    'template_id' => (string) $template->id,
                    'workshop_id' => (string) $template->workshop_id,
                    'vehicle_id' => (string) $vehicle->id,
                    'trigger' => $template->trigger->value,
                    'next_due_kilometers' => (string) ($nextDueKm ?? ''),
                    'source_maintenance_id' => (string) ($sourceMaintenanceId ?? ''),
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
