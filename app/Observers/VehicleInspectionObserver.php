<?php

namespace App\Observers;

use App\Models\VehicleInspection;
use App\Services\Vehicle\VehicleOdometerLog;

/**
 * Projeta o hodômetro do laudo da cautelar na tabela de leituras.
 *
 * A leitura entra no histórico e **nunca** conta para o hodômetro do veículo
 * (`counts_toward_odometer` nasce false em VehicleOdometerLog::recordFromInspection): o laudo pode
 * estar justamente relatando adulteração, e alimentar o km do veículo com ele é o que
 * .ai/rules/inspections.md proíbe. Por isso contestação e supressão do laudo não têm gancho aqui —
 * não há o que tirar de uma conta em que a leitura nunca entrou.
 */
class VehicleInspectionObserver
{
    public function __construct(private readonly VehicleOdometerLog $log) {}

    public function created(VehicleInspection $inspection): void
    {
        $this->log->recordFromInspection($inspection);
    }

    public function updated(VehicleInspection $inspection): void
    {
        if ($inspection->wasChanged(['odometer_km', 'inspected_at', 'vehicle_id'])) {
            $this->log->recordFromInspection($inspection);
        }
    }
}
