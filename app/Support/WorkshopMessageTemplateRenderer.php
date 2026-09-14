<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;

class WorkshopMessageTemplateRenderer
{
    /**
     * @param  array{
     *     workshop_name?: string,
     *     customer_name?: string,
     *     vehicle?: string,
     *     estimated_km?: int|string,
     *     next_due_km?: int|string,
     *     days_since_service?: int|string,
     *     last_service?: string,
     * }  $context
     */
    public function render(string $template, array $context): string
    {
        $replacements = [
            '{{workshop_name}}' => e((string) ($context['workshop_name'] ?? '')),
            '{{customer_name}}' => e((string) ($context['customer_name'] ?? '')),
            '{{vehicle}}' => e((string) ($context['vehicle'] ?? '')),
            '{{estimated_km}}' => e($this->formatKilometers($context['estimated_km'] ?? '')),
            '{{next_due_km}}' => e($this->formatKilometers($context['next_due_km'] ?? '')),
            '{{days_since_service}}' => e((string) ($context['days_since_service'] ?? '')),
            '{{last_service}}' => e((string) ($context['last_service'] ?? '')),
        ];

        return strtr($template, $replacements);
    }

    public function buildContext(
        User $user,
        Vehicle $vehicle,
        Workshop $workshop,
        int $estimatedKm,
        ?int $nextDueKm = null,
        ?Maintenance $sourceMaintenance = null,
    ): array {
        return [
            'workshop_name' => $workshop->name,
            'customer_name' => $this->customerFirstName($user),
            'vehicle' => trim("{$vehicle->brand} {$vehicle->model}"),
            'estimated_km' => $estimatedKm,
            'next_due_km' => $nextDueKm,
            'days_since_service' => $sourceMaintenance?->maintenance_date?->diffInDays(now()),
            'last_service' => $sourceMaintenance?->maintenance_date?->format('d/m/Y'),
        ];
    }

    private function customerFirstName(User $user): string
    {
        $firstName = trim(explode(' ', trim($user->name), 2)[0] ?? '');

        return $firstName !== '' ? $firstName : 'motorista';
    }

    private function formatKilometers(int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((int) $value, 0, ',', '.');
    }
}
