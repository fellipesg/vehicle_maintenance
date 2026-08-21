<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;

class VehicleMaintenanceReminderService
{
    /**
     * @return array{
     *     interval_kilometers: int,
     *     anchor_kilometers: int|null,
     *     next_due_kilometers: int|null,
     *     kilometers_remaining: int|null,
     *     is_overdue: bool,
     *     progress_percent: float|null,
     *     notify_before_kilometers: int
     * }
     */
    public function summarize(Vehicle $vehicle): array
    {
        $interval = max(1, (int) config('maintenance_intervals.default_preventive_kilometers', 10_000));
        $current = (int) ($vehicle->current_kilometers ?? 0);
        $anchor = $this->anchorKilometers($vehicle);

        if ($anchor === null && $current <= 0) {
            return [
                'interval_kilometers' => $interval,
                'anchor_kilometers' => null,
                'next_due_kilometers' => null,
                'kilometers_remaining' => null,
                'is_overdue' => false,
                'progress_percent' => null,
                'notify_before_kilometers' => (int) config('maintenance_intervals.notify_before_kilometers', 2_000),
            ];
        }

        $anchor ??= $current;
        $nextDue = $this->nextDueKilometers($current, $anchor, $interval);
        $remaining = max(0, $nextDue - $current);
        $isOverdue = $current >= $nextDue;
        $progress = $this->progressPercent($anchor, $nextDue, $current, $interval);

        return [
            'interval_kilometers' => $interval,
            'anchor_kilometers' => $anchor,
            'next_due_kilometers' => $nextDue,
            'kilometers_remaining' => $isOverdue ? 0 : $remaining,
            'is_overdue' => $isOverdue,
            'progress_percent' => $progress,
            'notify_before_kilometers' => (int) config('maintenance_intervals.notify_before_kilometers', 2_000),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function upcomingTimelineEvent(Vehicle $vehicle): ?array
    {
        $summary = $this->summarize($vehicle);

        if ($summary['next_due_kilometers'] === null) {
            return null;
        }

        return [
            'type' => 'upcoming',
            'id' => 'upcoming-preventive',
            'label' => (string) config('maintenance_intervals.labels.upcoming', 'Próxima revisão estimada'),
            'description' => 'Estimativa com base na última revisão registrada e intervalo de '
                .number_format($summary['interval_kilometers'], 0, ',', '.').' km.',
            'date' => null,
            'kilometers' => $summary['next_due_kilometers'],
            'kilometers_remaining' => $summary['kilometers_remaining'],
            'is_overdue' => $summary['is_overdue'],
            'total_amount' => 0.0,
            'items' => [],
            'estimated' => true,
        ];
    }

    public function shouldNotify(Vehicle $vehicle): bool
    {
        $summary = $this->summarize($vehicle);

        if ($summary['next_due_kilometers'] === null || $summary['is_overdue']) {
            return $summary['is_overdue'];
        }

        return ($summary['kilometers_remaining'] ?? PHP_INT_MAX)
            <= ($summary['notify_before_kilometers'] ?? 2_000);
    }

    private function anchorKilometers(Vehicle $vehicle): ?int
    {
        $maintenanceKm = $vehicle->maintenances
            ->filter(fn (Maintenance $maintenance) => $maintenance->kilometers !== null)
            ->max('kilometers');

        if ($maintenanceKm !== null) {
            return (int) $maintenanceKm;
        }

        if ($vehicle->odometer_at_registration !== null) {
            return (int) $vehicle->odometer_at_registration;
        }

        return $vehicle->current_kilometers !== null ? (int) $vehicle->current_kilometers : null;
    }

    private function nextDueKilometers(int $currentKm, int $anchorKm, int $intervalKm): int
    {
        $next = $anchorKm + $intervalKm;

        while ($next <= $currentKm) {
            $next += $intervalKm;
        }

        return $next;
    }

    private function progressPercent(int $anchorKm, int $nextDueKm, int $currentKm, int $intervalKm): ?float
    {
        $progressStart = $anchorKm;

        if ($progressStart >= $currentKm) {
            $progressStart = $nextDueKm - $intervalKm;

            while ($progressStart >= $currentKm && $progressStart > 0) {
                $progressStart -= $intervalKm;
            }
        }

        $span = $nextDueKm - $progressStart;

        if ($span <= 0) {
            return null;
        }

        $progress = (($currentKm - $progressStart) / $span) * 100;

        return round(min(100, max(0, $progress)), 1);
    }
}
