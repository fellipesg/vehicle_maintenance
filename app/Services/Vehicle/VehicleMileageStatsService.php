<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use Carbon\Carbon;

class VehicleMileageStatsService
{
    /**
     * @return array{
     *     approximate_annual_kilometers: int|null,
     *     kilometers_driven: int|null,
     *     period_days: int|null,
     *     period_start_date: string|null,
     *     period_end_date: string|null,
     *     is_approximate: bool
     * }|null
     */
    public function approximateAnnualKilometers(Vehicle $vehicle): ?array
    {
        $points = $this->mileagePoints($vehicle);

        if (count($points) < 2) {
            return null;
        }

        usort($points, fn (array $a, array $b) => $a['date']->getTimestamp() <=> $b['date']->getTimestamp());

        $first = $points[0];
        $last = $points[array_key_last($points)];

        $kilometersDriven = (int) $last['kilometers'] - (int) $first['kilometers'];

        if ($kilometersDriven <= 0) {
            return null;
        }

        $periodDays = max(1, $first['date']->diffInDays($last['date']));

        if ($periodDays < 30) {
            return null;
        }

        $years = $periodDays / 365.25;
        $annualKm = (int) round($kilometersDriven / $years);

        return [
            'approximate_annual_kilometers' => $annualKm,
            'kilometers_driven' => $kilometersDriven,
            'period_days' => $periodDays,
            'period_start_date' => $first['date']->toDateString(),
            'period_end_date' => $last['date']->toDateString(),
            'is_approximate' => true,
        ];
    }

    /**
     * @return list<array{kilometers: int, date: Carbon}>
     */
    private function mileagePoints(Vehicle $vehicle): array
    {
        $points = [];

        if ($vehicle->odometer_at_registration !== null && $vehicle->created_at !== null) {
            $points[] = [
                'kilometers' => (int) $vehicle->odometer_at_registration,
                'date' => $vehicle->created_at->copy()->startOfDay(),
            ];
        }

        foreach ($vehicle->maintenances as $maintenance) {
            if ($maintenance->kilometers === null || $maintenance->maintenance_date === null) {
                continue;
            }

            $points[] = [
                'kilometers' => (int) $maintenance->kilometers,
                'date' => $maintenance->maintenance_date->copy()->startOfDay(),
            ];
        }

        return $this->deduplicatePoints($points);
    }

    /**
     * @param  list<array{kilometers: int, date: Carbon}>  $points
     * @return list<array{kilometers: int, date: Carbon}>
     */
    private function deduplicatePoints(array $points): array
    {
        $unique = [];

        foreach ($points as $point) {
            $key = $point['date']->toDateString().':'.$point['kilometers'];
            $unique[$key] = $point;
        }

        return array_values($unique);
    }
}
