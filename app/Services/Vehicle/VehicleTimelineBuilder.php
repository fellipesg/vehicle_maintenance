<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;

class VehicleTimelineBuilder
{
    public function __construct(
        private readonly VehicleMaintenanceReminderService $reminders,
        private readonly VehicleMileageStatsService $mileageStats,
    ) {}

    /**
     * @return array{
     *     vehicle: array<string, mixed>,
     *     registration: array<string, mixed>|null,
     *     events: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     reminder: array<string, mixed>
     * }
     */
    public function build(Vehicle $vehicle): array
    {
        $vehicle->load(['maintenances.items', 'maintenances.workshop', 'maintenances.invoices']);

        $events = [];
        $currentKm = (int) ($vehicle->current_kilometers ?? 0);

        $registration = $this->resolveRegistrationAnchor($vehicle);

        if ($registration !== null) {
            $hasMaintenanceAtSameKm = $vehicle->maintenances->contains(
                fn ($maintenance) => $maintenance->kilometers !== null
                    && (int) $maintenance->kilometers === $registration['kilometers'],
            );

            if (! $hasMaintenanceAtSameKm) {
                $events[] = [
                    'type' => 'registration',
                    'id' => 'registration',
                    'label' => 'Cadastro do veículo',
                    'date' => $registration['date'],
                    'kilometers' => $registration['kilometers'],
                    'total_amount' => 0.0,
                    'items' => [],
                    'is_current' => $currentKm === $registration['kilometers'],
                ];
            }
        }

        foreach ($vehicle->maintenances as $maintenance) {
            if ($maintenance->kilometers === null) {
                continue;
            }

            $items = $maintenance->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                'total_price' => $item->total_price !== null ? (float) $item->total_price : null,
            ])->values()->all();

            $events[] = [
                'type' => 'maintenance',
                'id' => $maintenance->id,
                'label' => $maintenance->maintenance_type,
                'description' => $maintenance->description,
                'workshop_name' => $maintenance->displayWorkshopName(),
                'service_category' => $maintenance->service_category,
                'date' => $maintenance->maintenance_date->toDateString(),
                'kilometers' => (int) $maintenance->kilometers,
                'total_amount' => round(collect($items)->sum(fn (array $item) => $item['total_price'] ?? 0), 2),
                'items_count' => count($items),
                'has_invoice' => $maintenance->invoices->isNotEmpty(),
                'items' => $items,
                'is_current' => false,
            ];
        }

        usort($events, fn (array $a, array $b) => ((int) ($a['kilometers'] ?? 0)) <=> ((int) ($b['kilometers'] ?? 0)));

        $this->markCurrentEvent($events, $currentKm);

        $upcoming = $this->reminders->upcomingTimelineEvent($vehicle);
        if ($upcoming !== null) {
            $events[] = $upcoming;
        }

        $maintenanceEvents = array_values(array_filter(
            $events,
            fn (array $event) => $event['type'] === 'maintenance',
        ));

        $reminder = $this->reminders->summarize($vehicle);
        $usage = $this->mileageStats->approximateAnnualKilometers($vehicle);

        return [
            'vehicle' => [
                'id' => $vehicle->id,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'license_plate' => $vehicle->license_plate,
                'year' => $vehicle->year,
                'cover_photo_url' => $vehicle->cover_photo_url,
                'current_kilometers' => $vehicle->current_kilometers,
            ],
            'registration' => $vehicle->odometer_at_registration !== null ? [
                'kilometers' => $vehicle->odometer_at_registration,
                'date' => $vehicle->created_at?->toDateString(),
            ] : null,
            'events' => $events,
            'reminder' => $reminder,
            'summary' => [
                'maintenance_count' => count($maintenanceEvents),
                'total_spent' => round(collect($maintenanceEvents)->sum('total_amount'), 2),
                'first_kilometers' => collect($events)->pluck('kilometers')->filter()->min(),
                'last_kilometers' => $vehicle->current_kilometers,
                'next_due_kilometers' => $reminder['next_due_kilometers'],
                'kilometers_remaining' => $reminder['kilometers_remaining'],
                'progress_percent' => $reminder['progress_percent'],
                'is_overdue' => $reminder['is_overdue'],
                'approximate_annual_kilometers' => $usage['approximate_annual_kilometers'] ?? null,
                'usage_is_approximate' => $usage !== null,
                'usage_period_start_date' => $usage['period_start_date'] ?? null,
                'usage_period_end_date' => $usage['period_end_date'] ?? null,
            ],
        ];
    }

    /**
     * @return array{kilometers: int, date: string|null}|null
     */
    private function resolveRegistrationAnchor(Vehicle $vehicle): ?array
    {
        if ($vehicle->odometer_at_registration !== null) {
            return [
                'kilometers' => (int) $vehicle->odometer_at_registration,
                'date' => $vehicle->created_at?->toDateString(),
            ];
        }

        $firstMaintenance = $vehicle->maintenances
            ->filter(fn ($maintenance) => $maintenance->kilometers !== null)
            ->sortBy('kilometers')
            ->first();

        if ($firstMaintenance !== null) {
            return [
                'kilometers' => (int) $firstMaintenance->kilometers,
                'date' => $firstMaintenance->maintenance_date->toDateString(),
            ];
        }

        if ($vehicle->current_kilometers !== null) {
            return [
                'kilometers' => (int) $vehicle->current_kilometers,
                'date' => $vehicle->created_at?->toDateString(),
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function markCurrentEvent(array &$events, int $currentKm): void
    {
        $bestIndex = null;
        $bestKm = -1;

        foreach ($events as $index => $event) {
            if (($event['type'] ?? '') === 'upcoming') {
                continue;
            }

            $km = (int) ($event['kilometers'] ?? -1);
            if ($km <= $currentKm && $km >= $bestKm) {
                $bestKm = $km;
                $bestIndex = $index;
            }
        }

        if ($bestIndex !== null) {
            $events[$bestIndex]['is_current'] = true;
        }
    }
}
