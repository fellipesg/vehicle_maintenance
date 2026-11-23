<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOdometerLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Preenche `vehicle_odometer_readings` com o km que já estava gravado nas colunas de origem, e
 * confere se a projeção reproduz o número que o sistema usa hoje.
 *
 * Os observers (MaintenanceObserver, VehicleObserver, VehicleInspectionObserver) só alcançam o que
 * for gravado **a partir** deles. Todo o histórico anterior precisa de uma passada, e é esta.
 *
 * **Manual, de propósito, e fora do deploy.** Rodar junto do deploy acopla a liberação de código a
 * uma varredura de todos os veículos: se ela demorar ou falhar no meio, a liberação para junto, e o
 * que se ganha com isso é nada — a etapa seguinte (trocar o cálculo de `current_kilometers`) é outra
 * liberação, decidida depois de `--check` dar zero. Também não entra no `Schedule`: um backfill que
 * roda sozinho toda noite esconde o dia em que passou a divergir.
 *
 * Rodar de novo é seguro. Cada leitura é upsert pela origem (o `unique` em `maintenance_id` e em
 * `vehicle_inspection_id` garante no banco), então a segunda passada corrige em vez de empilhar.
 *
 * Ordem de uso:
 *
 *   php artisan odometer:backfill-readings --dry-run   # quanto tem para fazer
 *   php artisan odometer:backfill-readings             # faz
 *   php artisan odometer:backfill-readings --check     # confere, sem escrever
 *
 * `--check` é o portão da etapa seguinte: ele compara, veículo por veículo, o maior km entre as
 * leituras que contam com a fórmula antiga (`max(odometer_at_registration, maior km de OS que
 * conta)`) e **sai com código de erro** se alguma divergir. Enquanto ele não der zero, trocar o
 * cálculo de `current_kilometers` mudaria o número de algum veículo sem ninguém saber qual.
 */
class BackfillOdometerReadingsCommand extends Command
{
    protected $signature = 'odometer:backfill-readings
        {--check : Só compara a projeção com a fórmula antiga e sai com erro se divergir; não escreve}
        {--dry-run : Só conta o que seria gravado; não escreve}
        {--limit=0 : Para depois de N veículos (0 = sem limite)}
        {--vehicle= : Só o veículo deste id}';

    protected $description = 'Preenche as leituras do odômetro a partir do km já gravado e confere a projeção (manual, fora do deploy)';

    private int $vehicles = 0;

    private int $registrations = 0;

    private int $maintenances = 0;

    private int $inspections = 0;

    private int $diverging = 0;

    /**
     * Veículos em que `vehicles.current_kilometers` já não é o que a fórmula antiga devolve —
     * divergência que existe hoje, antes de qualquer projeção, e que esta passada não causou nem
     * conserta. Relatada à parte para não ser confundida com a divergência que importa.
     */
    private int $alreadyStale = 0;

    public function handle(VehicleOdometerLog $log): int
    {
        // O console reaproveita a instância do comando, então duas chamadas no mesmo processo
        // (`Artisan::call` seguido de outro, e a suíte) somariam os contadores da primeira — foi
        // assim que um --check logo depois de outro relatou divergência que já tinha sido corrigida.
        $this->vehicles = 0;
        $this->registrations = 0;
        $this->maintenances = 0;
        $this->inspections = 0;
        $this->diverging = 0;
        $this->alreadyStale = 0;

        $limit = max(0, (int) $this->option('limit'));
        $check = (bool) $this->option('check');
        $dryRun = (bool) $this->option('dry-run');

        if ($check && $dryRun) {
            $this->error('Use --check ou --dry-run, não os dois: um confere o que está lá, o outro conta o que falta.');

            return self::FAILURE;
        }

        $this->line(match (true) {
            $check => 'Conferindo a projeção contra a fórmula antiga (nada será escrito).',
            $dryRun => 'Contando o que seria gravado (nada será escrito).',
            default => 'Preenchendo as leituras do odômetro.',
        });

        $query = Vehicle::query()
            ->with(['maintenances', 'inspections'])
            ->orderBy('id');

        if (($id = $this->option('vehicle')) !== null) {
            $query->whereKey((int) $id);
        }

        $stop = false;

        $query->chunkById(100, function (Collection $vehicles) use ($log, $check, $dryRun, $limit, &$stop): bool {
            foreach ($vehicles as $vehicle) {
                if ($limit > 0 && $this->vehicles >= $limit) {
                    $stop = true;

                    return false;
                }

                $this->vehicles++;

                if ($check) {
                    $this->checkVehicle($log, $vehicle);

                    continue;
                }

                $dryRun
                    ? $this->countVehicle($vehicle)
                    : $this->backfillVehicle($log, $vehicle);
            }

            return true;
        });

        if ($stop) {
            $this->comment("Parou no limite de {$limit} veículo(s).");
        }

        return $check ? $this->reportCheck() : $this->reportWrite($dryRun);
    }

    /**
     * Grava as leituras deste veículo. O escritor é o mesmo dos observers, de propósito: backfill
     * que calcula por conta própria é backfill que discorda da produção.
     */
    private function backfillVehicle(VehicleOdometerLog $log, Vehicle $vehicle): void
    {
        if ($log->syncRegistration($vehicle) !== null) {
            $this->registrations++;
        }

        foreach ($vehicle->maintenances as $maintenance) {
            if ($log->recordFromMaintenance($maintenance) !== null) {
                $this->maintenances++;
            }
        }

        foreach ($vehicle->inspections as $inspection) {
            if ($log->recordFromInspection($inspection) !== null) {
                $this->inspections++;
            }
        }

        if ($this->output->isVerbose()) {
            $this->line("  #{$vehicle->id}: ".$vehicle->odometerReadings()->count().' leitura(s)');
        }
    }

    /**
     * O mesmo levantamento do backfill, sem escrever: quantas leituras aquele veículo deve ter.
     */
    private function countVehicle(Vehicle $vehicle): void
    {
        if ($vehicle->odometer_at_registration !== null) {
            $this->registrations++;
        }

        $this->maintenances += $vehicle->maintenances
            ->filter(fn ($maintenance): bool => $maintenance->kilometers !== null)
            ->count();

        $this->inspections += $vehicle->inspections
            ->filter(fn ($inspection): bool => $inspection->odometer_km !== null)
            ->count();
    }

    /**
     * A projeção reproduz o número que o sistema usa hoje?
     *
     * A comparação é contra a **fórmula**, não contra `vehicles.current_kilometers`: é a fórmula que
     * a etapa seguinte substitui, e a coluna pode estar velha por um registro gravado antes de o
     * recálculo existir (contado em `alreadyStale`, à parte).
     */
    private function checkVehicle(VehicleOdometerLog $log, Vehicle $vehicle): void
    {
        $fromFormula = max(
            (int) ($vehicle->odometer_at_registration ?? 0),
            (int) $vehicle->maintenances()->countsTowardOdometer()->max('kilometers'),
        );

        $fromReadings = $log->highestCountedKilometers($vehicle);

        if ($fromReadings !== $fromFormula) {
            $this->diverging++;
            $this->warn(sprintf(
                '  #%d (%s): fórmula %s km, leituras %s km',
                $vehicle->id,
                $vehicle->license_plate ?? 'sem placa',
                number_format($fromFormula, 0, ',', '.'),
                number_format($fromReadings, 0, ',', '.'),
            ));
        }

        if ((int) ($vehicle->current_kilometers ?? 0) !== $fromFormula) {
            $this->alreadyStale++;
        }
    }

    private function reportCheck(): int
    {
        $this->newLine();
        $this->line("Veículos conferidos: {$this->vehicles}");

        if ($this->alreadyStale > 0) {
            $this->comment(
                "{$this->alreadyStale} veículo(s) já têm current_kilometers diferente da própria "
                .'fórmula antiga (divergência anterior à projeção; vehicles:refresh-kilometers resolve).'
            );
        }

        if ($this->diverging > 0) {
            $this->error("{$this->diverging} veículo(s) divergem entre a projeção e a fórmula antiga.");
            $this->line('Rode o backfill (sem --check) e confira de novo. Enquanto não der zero, não troque o cálculo de current_kilometers.');

            return self::FAILURE;
        }

        $this->info('Nenhuma divergência: a projeção reproduz a fórmula antiga em todos os veículos conferidos.');

        return self::SUCCESS;
    }

    private function reportWrite(bool $dryRun): int
    {
        $verb = $dryRun ? 'seriam gravadas' : 'gravadas';

        $this->newLine();
        $this->line("Veículos: {$this->vehicles}");
        $this->line("Leituras {$verb} — cadastro: {$this->registrations}, OS: {$this->maintenances}, laudo: {$this->inspections}");

        if ($dryRun) {
            $this->comment('Nada foi escrito. Rode sem --dry-run para gravar.');

            return self::SUCCESS;
        }

        $this->info('Concluído. Confira com --check antes de trocar o cálculo de current_kilometers.');

        return self::SUCCESS;
    }
}
