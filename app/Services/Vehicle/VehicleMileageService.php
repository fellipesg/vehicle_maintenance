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
     * Folga fixa do teto de plausibilidade, em km (ver plausibilityCeiling()).
     */
    private const PLAUSIBILITY_BASE_KILOMETERS = 50_000;

    /**
     * Folga do teto de plausibilidade por ano de vida do veículo, em km (ver plausibilityCeiling()).
     */
    private const PLAUSIBILITY_KILOMETERS_PER_YEAR = 100_000;

    /**
     * Garante que a quilometragem informada é coerente com a linha do tempo do veículo.
     *
     * O hodômetro do cadastro só serve de piso para manutenções feitas a partir da data
     * de cadastro. Manutenções anteriores (histórico) só precisam respeitar os registros
     * vizinhos: no mínimo o km do registro anterior e no máximo o km do registro seguinte.
     *
     * Acima disso vale o teto de plausibilidade derivado do ano do veículo (plausibilityCeiling()),
     * que é o único limite quando não existe registro posterior — o caso comum de uma oficina
     * registrando pela primeira vez.
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

        $plausible = $this->plausibilityCeiling($vehicle, $floor);

        if ($plausible !== null && $kilometers > $plausible) {
            throw ValidationException::withMessages([
                'kilometers' => "A quilometragem deve ser no máximo {$plausible} km: é o teto plausível para um veículo {$vehicle->year}. Confira se não sobrou um dígito.",
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
                (int) $vehicle->maintenances()->countsTowardOdometer()->max('kilometers'),
                $kilometers,
            ),
        ]);
    }

    public function refreshCurrentKilometers(Vehicle $vehicle): void
    {
        $vehicle->update([
            'current_kilometers' => max(
                (int) ($vehicle->odometer_at_registration ?? 0),
                (int) $vehicle->maintenances()->countsTowardOdometer()->max('kilometers'),
            ),
        ]);
    }

    private function minimumAllowedKilometers(Vehicle $vehicle, ?CarbonImmutable $date, ?Maintenance $except): int
    {
        $query = $vehicle->maintenances()->countsTowardOdometer()->whereNotNull('kilometers');

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
            ->countsTowardOdometer()
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

    /**
     * Teto do que o veículo pode plausivelmente ter rodado, derivado do ano dele.
     *
     * Sem isso, `max:9999999` nas validações era o único limite: nada impedia gravar 500.000 km num
     * carro 2024. E o número não fica só no registro — ele vira o `current_kilometers` do veículo e
     * o piso de toda OS seguinte, então um dígito a mais trancava o veículo até um admin ocultar o
     * registro (MaintenancePublicVisibilityService). Recusar na entrada é mais barato que desfazer.
     *
     * A folga é deliberadamente grande, porque recusar um veículo real é pior que aceitar um número
     * alto: PLAUSIBILITY_BASE_KILOMETERS (50.000 km) cobre o veículo que sai da fábrica ou da
     * importação com km rodado, o ano-modelo à frente do calendário e o carro novo em uso intenso;
     * PLAUSIBILITY_KILOMETERS_PER_YEAR (100.000 km por ano de vida) são ~274 km por dia, todos os
     * dias, contra os 13.000 km/ano da média brasileira
     * (`config('maintenance_intervals.average_kilometers_per_year')`) e os 50.000 a 80.000 de um
     * táxi ou motorista de aplicativo. O ano-modelo conta como ano inteiro, então até um veículo do
     * ano corrente tem 150.000 km de margem. Só pega quem digitou um dígito a mais.
     *
     * Dois casos em que o teto não recusa um veículo real:
     *
     * - **veículo sem ano conhecido** (cadastro antigo, ou ano gravado como zero): não há de onde
     *   derivar, e chutar um ano seria recusar por chute. Devolve null, sem teto nenhum.
     * - **veículo que já roda acima do teto derivado do ano**, legitimamente (frota, caminhão, veículo antigo com
     *   rodagem alta) ou não (erro ainda não suprimido): o teto nunca desce até o que o veículo já
     *   registra, e sim um ano de folga acima disso. Parar exatamente na leitura atual deixaria a
     *   faixa aceita vazia — o veículo não conseguiria somar nem um km — e é o que a regra existe
     *   para não fazer: recusar quem roda muito.
     */
    private function plausibilityCeiling(Vehicle $vehicle, int $floor): ?int
    {
        $year = (int) ($vehicle->year ?? 0);

        if ($year < 1900) {
            return null;
        }

        $yearsOfLife = max(0, CarbonImmutable::now()->year - $year) + 1;

        $alreadyRun = max(
            $floor,
            (int) ($vehicle->current_kilometers ?? 0),
            (int) ($vehicle->odometer_at_registration ?? 0),
        );

        return max(
            self::PLAUSIBILITY_BASE_KILOMETERS + ($yearsOfLife * self::PLAUSIBILITY_KILOMETERS_PER_YEAR),
            $alreadyRun + self::PLAUSIBILITY_KILOMETERS_PER_YEAR,
        );
    }

    /**
     * Veículo criado pela oficina pelo chassi nasce sem hodômetro de cadastro e sem placa: não há
     * leitura "no cadastro" a usar de teto, e o km corrente (que só cresce com as OS) travaria o
     * registro de serviços antigos.
     */
    private function registrationKilometers(Vehicle $vehicle): ?int
    {
        if ($vehicle->odometer_at_registration === null && $vehicle->license_plate === null) {
            return null;
        }

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
