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
use App\Support\Workshop\WorkshopMessageConsent;
use App\Support\WorkshopMessageTemplateRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkshopMessageTemplateDispatcher
{
    /**
     * Veículos (ou OS) por lote na rodada diária.
     */
    private const CHUNK = 100;

    /**
     * Contas que já receberam mensagem de oficina dentro do intervalo mínimo, nesta rodada: a
     * consulta sai uma vez por conta, e o que esta rodada manda também conta. Zerada no começo de
     * dispatchFollowUps().
     *
     * @var array<int, bool>
     */
    private array $messagedRecently = [];

    public function __construct(
        private readonly VehicleEstimatedKmService $estimatedKm,
        private readonly VehicleMaintenanceReminderService $reminders,
        private readonly WorkshopMessageTemplateRenderer $renderer,
        private readonly WorkshopMessageConsent $consent,
    ) {}

    public function dispatchFollowUps(): int
    {
        $sent = 0;
        $this->messagedRecently = [];

        WorkshopMessageTemplate::query()
            ->where('is_active', true)
            // Modelo ativo com gatilho sem rodada (WorkshopMessageTrigger::isDispatchable) fica de
            // fora: ele não tem o que disparar, e no match abaixo derrubava a rodada de todas as
            // oficinas com UnhandledMatchError.
            ->whereIn('trigger', WorkshopMessageTrigger::dispatchableValues())
            ->with('workshop')
            ->orderBy('id')
            ->chunkById(50, function (Collection $templates) use (&$sent): void {
                foreach ($templates as $template) {
                    $sent += match ($template->trigger) {
                        WorkshopMessageTrigger::ScheduledRevision => $this->dispatchScheduledTemplate($template),
                        WorkshopMessageTrigger::CorrectiveFollowUp => $this->dispatchCorrectiveTemplate($template),
                        // Gatilho marcado com rodada antes de a rodada existir: nada sai, mas a
                        // rodada das outras oficinas continua.
                        default => 0,
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

        // Sem a janela, uma oficina que liga o modelo hoje dispara para todo o histórico dela de
        // uma vez: a OS de três anos atrás também está "a mais de minDays do serviço".
        $windowDays = (int) config('workshop_messages.send_window_days', 60);

        if ($windowDays > 0) {
            $maintenancesQuery->whereDate(
                'maintenance_date',
                '>=',
                now()->subDays($minDays + $windowDays)->startOfDay(),
            );
        }

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
        if (! $this->consent->allows($owner->id, $template->workshop_id)) {
            return false;
        }

        if ($this->alreadyDispatched($template, $owner, $vehicle, $dedupeKey)) {
            return false;
        }

        // Fora do intervalo mínimo o envio não acontece e nada é gravado: a próxima rodada tenta de
        // novo, porque o dedupe desta oportunidade continua livre.
        if ($this->isWithinQuietPeriod($owner)) {
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

        $this->messagedRecently[$owner->id] = true;

        $this->sendPushNotification($owner, $template, $vehicle, $title, $body, $nextDueKm, $sourceMaintenance?->id);

        return true;
    }

    /**
     * O cliente recebeu mensagem de oficina há pouco?
     *
     * O intervalo mínimo (config('workshop_messages.min_days_between_messages_per_user')) vale
     * somando todas as oficinas e todos os modelos: duas oficinas que atenderam o mesmo carro, ou
     * dois modelos da mesma oficina, não falam com a pessoa na mesma semana. A coluna sent_at tem
     * índice com user_id para esta consulta. Zero ou menos desliga a guarda.
     */
    private function isWithinQuietPeriod(User $owner): bool
    {
        $minDays = (int) config('workshop_messages.min_days_between_messages_per_user', 7);

        if ($minDays <= 0) {
            return false;
        }

        return $this->messagedRecently[$owner->id] ??= WorkshopMessageDispatch::query()
            ->where('user_id', $owner->id)
            ->where('sent_at', '>=', now()->subDays($minDays))
            ->exists();
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
