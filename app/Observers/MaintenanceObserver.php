<?php

namespace App\Observers;

use App\Models\Maintenance;
use App\Services\Vehicle\VehicleOdometerLog;

/**
 * Projeta o km da OS na tabela de leituras do odômetro (vehicle_odometer_readings).
 *
 * Gancho de model, e não chamada nos controllers, porque são seis superfícies que gravam OS (web do
 * proprietário, web da oficina, web do lojista, API, contestação e supressão) e a projeção não pode
 * depender de nenhuma delas lembrar. A OS continua dona do número; aqui a leitura acompanha.
 *
 * Mão única: este observer só escreve em `vehicle_odometer_readings`, nunca de volta em
 * `maintenances` — do contrário o gancho se chamaria a si mesmo.
 */
class MaintenanceObserver
{
    public function __construct(private readonly VehicleOdometerLog $log) {}

    public function created(Maintenance $maintenance): void
    {
        $this->log->recordFromMaintenance($maintenance);
    }

    /**
     * Três mudanças diferentes, e só uma delas reescreve a leitura.
     *
     * Km, data e veículo mudam o **que** foi lido, então a leitura é regravada (e apagada quando o
     * km virou nulo: `maintenances.kilometers` é nulável, e OS sem km não é leitura de odômetro).
     *
     * Contestação e supressão não mexem no número, só em **quem conta** — então viram a chave
     * `counts_toward_odometer` sem reescrever o resto. É o que faz dispute, withdrawDispute, decide,
     * hide e restore chegarem à projeção sem que nenhum dos cinco serviços saiba dela.
     */
    public function updated(Maintenance $maintenance): void
    {
        if ($maintenance->wasChanged(['kilometers', 'maintenance_date', 'vehicle_id'])) {
            $this->log->recordFromMaintenance($maintenance);

            return;
        }

        if ($maintenance->wasChanged(['owner_disputed_at', 'hidden_by_user_id'])) {
            $this->log->syncCounts($maintenance);
        }
    }

    /**
     * Nada a fazer: `vehicle_odometer_readings.maintenance_id` é FK em cascata, e o model não usa
     * SoftDeletes. A OS vai embora e a leitura dela vai junto, no banco.
     */
    public function deleted(Maintenance $maintenance): void {}
}
