<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VehicleMileageService
{
    /**
     * Garante que a quilometragem informada é coerente com a linha do tempo do veículo.
     *
     * O hodômetro do cadastro só serve de piso para manutenções feitas a partir da data
     * de cadastro. Manutenções anteriores (histórico) só precisam respeitar os registros
     * vizinhos: no mínimo o km do registro anterior e no máximo o km do registro seguinte.
     *
     * @throws ValidationException
     */
    public function assertMaintenanceKilometers(
        Vehicle $vehicle,
        int $kilometers,
        CarbonInterface|string|null $maintenanceDate = null,
        ?Maintenance $except = null,
    ): void {
        if ($kilometers < 0) {
            throw ValidationException::withMessages([
                'kilometers' => 'A quilometragem deve ser zero ou maior.',
            ]);
        }

        $date = $this->normalizeDate($maintenanceDate);

        $floor = $this->minimumAllowedKilometers($vehicle, $date, $except);

        if ($kilometers < $floor) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no mínimo {$floor} km (hodômetro ou manutenção já registrada até essa data).",
            ]);
        }

        $ceiling = $this->maximumAllowedKilometers($vehicle, $date, $except);

        if ($ceiling !== null && $kilometers > $ceiling) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no máximo {$ceiling} km, pois já existe registro posterior a essa data com essa quilometragem.",
            ]);
        }
    }

    public function registerOdometer(Vehicle $vehicle, int $kilometers): void
    {
        $vehicle->update([
            'current_kilometers' => $kilometers,
            'odometer_at_registration' => $kilometers,
        ]);
    }

    public function applyMaintenanceKilometers(Vehicle $vehicle, int $kilometers): void
    {
        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->odometer_at_registration ?? 0),
                (int) $vehicle->maintenances()->max('kilometers'),
                $kilometers,
            ),
        ]);
    }

    public function refreshCurrentKilometers(Vehicle $vehicle): void
    {
        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->odometer_at_registration ?? 0),
                (int) $vehicle->maintenances()->max('kilometers'),
            ),
        ]);
    }

    private function minimumAllowedKilometers(Vehicle $vehicle, ?CarbonImmutable $date, ?Maintenance $except): int
    {
        $query = $vehicle->maintenances()->whereNotNull('kilometers');

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        if ($date !== null) {
            $query->whereDate('maintenance_date', '<=', $date);
        }

        $floor = (int) ($query->max('kilometers') ?? 0);

        if ($date === null || $this->happensAfterRegistration($vehicle, $date)) {
            $floor = max($floor, $this->registrationKilometers($vehicle) ?? 0);
        }

        return $floor;
    }

    private function maximumAllowedKilometers(Vehicle $vehicle, ?CarbonImmutable $date, ?Maintenance $except): ?int
    {
        if ($date === null) {
            return null;
        }

        $query = $vehicle->maintenances()
            ->whereNotNull('kilometers')
            ->whereDate('maintenance_date', '>', $date);

        if ($except !== null) {
            $query->where('id', '!=', $except->id);
        }

        $ceiling = $query->min('kilometers');
        $ceiling = $ceiling === null ? null : (int) $ceiling;

        if (! $this->happensAfterRegistration($vehicle, $date)) {
            $registration = $this->registrationKilometers($vehicle);

            if ($registration !== null) {
                $ceiling = $ceiling === null ? $registration : min($ceiling, $registration);
            }
        }

        return $ceiling;
    }

    private function registrationKilometers(Vehicle $vehicle): ?int
    {
        $kilometers = $vehicle->odometer_at_registration ?? $vehicle->current_kilometers;

        return $kilometers === null ? null : (int) $kilometers;
    }

    private function happensAfterRegistration(Vehicle $vehicle, CarbonImmutable $date): bool
    {
        if ($vehicle->created_at === null) {
            return true;
        }

        return $date->greaterThanOrEqualTo(
            CarbonImmutable::parse($vehicle->created_at)->startOfDay(),
        );
    }

    private function normalizeDate(CarbonInterface|string|null $maintenanceDate): ?CarbonImmutable
    {
        if ($maintenanceDate === null || $maintenanceDate === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($maintenanceDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
