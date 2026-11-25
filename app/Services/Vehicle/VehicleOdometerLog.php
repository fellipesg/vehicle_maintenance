<?php

namespace App\Services\Vehicle;

use App\Enums\OdometerReadingSource;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleOdometerReading;

/**
 * O único escritor de `vehicle_odometer_readings`.
 *
 * A tabela é uma projeção: quem é dono do número continua sendo a origem (a OS, o cadastro, o
 * laudo), e aqui fica a cópia, de mão única. Por isso cada `recordFrom*` é um **upsert pela
 * origem** e nunca um insert cego — corrigir o km de uma OS corrige a leitura dela, em vez de
 * empilhar uma segunda.
 *
 * Único escritor por disciplina, não por gosto: `source` e `counts_toward_odometer` ficam fora do
 * fillable do model justamente para que nenhum controller marque autodeclaração como leitura de
 * oficina. É dessa distinção que depende o que pode alimentar taxa e teto de plausibilidade
 * (.ai/rules/models.md).
 */
class VehicleOdometerLog
{
    /**
     * O hodômetro informado no cadastro do veículo. É **uma** leitura por veículo, corrigida no
     * lugar quando a edição do veículo baixa o número: não é leitura nova, é a mesma leitura
     * consertada, e por isso `measured_at` continua sendo a data do cadastro.
     */
    public function recordRegistration(Vehicle $vehicle, int $kilometers, ?User $user = null): VehicleOdometerReading
    {
        $reading = VehicleOdometerReading::query()->firstOrNew([
            'vehicle_id' => $vehicle->id,
            'source' => OdometerReadingSource::Registration,
        ]);

        $reading->fill([
            'vehicle_id' => $vehicle->id,
            'kilometers' => max(0, $kilometers),
            'measured_at' => $vehicle->created_at?->toDateString() ?? now()->toDateString(),
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
        ]);

        $reading->source = OdometerReadingSource::Registration;
        $reading->counts_toward_odometer = true;
        $reading->save();

        return $reading;
    }

    /**
     * Espelha `vehicles.odometer_at_registration` na leitura do cadastro: grava quando a coluna tem
     * número, apaga quando ela ficou nula. É o que o VehicleObserver chama, e é a única entrada que
     * lê o km do próprio veículo em vez de recebê-lo — porque aqui o dono do número é a coluna.
     */
    public function syncRegistration(Vehicle $vehicle): ?VehicleOdometerReading
    {
        if ($vehicle->odometer_at_registration === null) {
            VehicleOdometerReading::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('source', OdometerReadingSource::Registration)
                ->delete();

            return null;
        }

        return $this->recordRegistration($vehicle, (int) $vehicle->odometer_at_registration);
    }

    /**
     * A leitura de uma OS. Devolve null — e apaga a leitura que houver — quando a OS está sem km:
     * `maintenances.kilometers` é nulável, e OS sem km não é leitura de odômetro.
     */
    public function recordFromMaintenance(Maintenance $maintenance): ?VehicleOdometerReading
    {
        if ($maintenance->kilometers === null || $maintenance->vehicle_id === null) {
            $this->forgetMaintenance($maintenance);

            return null;
        }

        $reading = VehicleOdometerReading::query()->firstOrNew([
            'maintenance_id' => $maintenance->id,
        ]);

        $reading->fill([
            'vehicle_id' => $maintenance->vehicle_id,
            'kilometers' => max(0, (int) $maintenance->kilometers),
            'measured_at' => $maintenance->maintenance_date?->toDateString() ?? now()->toDateString(),
            'user_id' => $maintenance->user_id,
            'workshop_id' => $maintenance->workshop_id,
            'tenant_id' => $maintenance->tenant_id,
        ]);

        $reading->source = OdometerReadingSource::Maintenance;
        $reading->maintenance_id = $maintenance->id;
        $reading->counts_toward_odometer = self::maintenanceCounts($maintenance);
        $reading->save();

        return $reading;
    }

    public function forgetMaintenance(Maintenance $maintenance): void
    {
        VehicleOdometerReading::query()
            ->where('maintenance_id', $maintenance->id)
            ->delete();
    }

    /**
     * A OS foi contestada, teve a contestação retirada, foi suprimida pela equipe ou restaurada —
     * nenhuma dessas mexe no km, só em quem conta. Vira a chave da leitura sem reescrever o resto.
     */
    public function syncCounts(Maintenance $maintenance): void
    {
        VehicleOdometerReading::query()
            ->where('maintenance_id', $maintenance->id)
            ->update(['counts_toward_odometer' => self::maintenanceCounts($maintenance)]);
    }

    /**
     * A leitura do laudo da vistoria cautelar. Entra no histórico e **nunca** conta para o
     * hodômetro: o laudo pode estar relatando adulteração, e alimentar o km do veículo com ela é o
     * que .ai/rules/inspections.md proíbe.
     */
    public function recordFromInspection(VehicleInspection $inspection): ?VehicleOdometerReading
    {
        if ($inspection->odometer_km === null) {
            VehicleOdometerReading::query()
                ->where('vehicle_inspection_id', $inspection->id)
                ->delete();

            return null;
        }

        $reading = VehicleOdometerReading::query()->firstOrNew([
            'vehicle_inspection_id' => $inspection->id,
        ]);

        $reading->fill([
            'vehicle_id' => $inspection->vehicle_id,
            'kilometers' => max(0, (int) $inspection->odometer_km),
            'measured_at' => $inspection->inspected_at?->toDateString() ?? now()->toDateString(),
            'workshop_id' => $inspection->workshop_id,
            'tenant_id' => $inspection->tenant_id,
        ]);

        $reading->source = OdometerReadingSource::Inspection;
        $reading->vehicle_inspection_id = $inspection->id;
        $reading->counts_toward_odometer = false;
        $reading->save();

        return $reading;
    }

    /**
     * A leitura que o proprietário informa (hoje por tela, adiante respondendo à notificação de
     * odômetro). É série temporal: cada resposta é uma leitura nova, não a correção da anterior.
     *
     * Quem chama confere o teto de plausibilidade antes
     * (VehicleMileageService::assertOdometerKilometers): aqui é gravação, não validação.
     */
    public function recordOwnerReport(Vehicle $vehicle, int $kilometers, User $user): VehicleOdometerReading
    {
        $reading = new VehicleOdometerReading([
            'vehicle_id' => $vehicle->id,
            'kilometers' => max(0, $kilometers),
            'measured_at' => now()->toDateString(),
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $reading->source = OdometerReadingSource::OwnerReport;
        $reading->counts_toward_odometer = true;
        $reading->save();

        return $reading;
    }

    /**
     * O maior km entre as leituras que contam — o que `vehicles.current_kilometers` **é**.
     *
     * VehicleMileageService::refreshCurrentKilometers grava esse número na coluna, e é o único
     * caminho até ela. Veículo sem leitura devolve zero: a projeção precisa estar completa, e é o
     * `odometer:backfill-readings --check` que garante isso antes do deploy.
     */
    public function highestCountedKilometers(Vehicle $vehicle): int
    {
        return (int) $vehicle->odometerReadings()
            ->countsTowardOdometer()
            ->max('kilometers');
    }

    /**
     * O mesmo maior km, limitado às origens que a **fórmula antiga** conhecia: o hodômetro do
     * cadastro e as OS que contam.
     *
     * Existe para o `odometer:backfill-readings --check`, e só para ele. A fórmula que o portão
     * compara não conhece autodeclaração do dono; incluí-la na outra ponta faria o portão acusar
     * divergência justamente onde a projeção está certa, e um portão que grita sozinho deixa de ser
     * lido. Quem quer o odômetro do veículo usa highestCountedKilometers.
     */
    public function highestCountedKilometersFromColumns(Vehicle $vehicle): int
    {
        return (int) $vehicle->odometerReadings()
            ->countsTowardOdometer()
            ->whereIn('source', [
                OdometerReadingSource::Registration,
                OdometerReadingSource::Maintenance,
            ])
            ->max('kilometers');
    }

    /**
     * Espelha Maintenance::scopeCountsTowardOdometer: fica de fora o que o proprietário contestou e
     * o que a equipe suprimiu. As duas formas precisam andar juntas.
     */
    private static function maintenanceCounts(Maintenance $maintenance): bool
    {
        return $maintenance->owner_disputed_at === null
            && $maintenance->hidden_by_user_id === null;
    }
}
