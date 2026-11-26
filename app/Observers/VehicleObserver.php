<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOdometerLog;

/**
 * Projeta o hodômetro do cadastro na tabela de leituras.
 *
 * Espelha **`odometer_at_registration`**, e só ele: é a coluna que guarda o número que alguém leu no
 * painel e informou, e o piso de toda OS seguinte. `current_kilometers` não é lido de painel nenhum
 * — é o derivado — e por isso não é leitura.
 *
 * Três lugares escrevem essa coluna com assinaturas diferentes (VehicleOwnershipService::registerNew
 * no cadastro do proprietário, VehicleMileageService::registerOdometer no veículo que a oficina
 * criou pelo chassi, e UpdatesVehicleDetails quando a edição baixa o número). O gancho cobre os três
 * de uma vez e cobre o quarto que ainda não existe.
 *
 * A leitura do cadastro nasce **sem usuário**, de propósito: a coluna também não tem um. Quem
 * cadastrou está no vínculo (`user_vehicles`), e o "quem registrou" que a leitura carrega é o das
 * origens que têm autor — a OS tem a oficina, o relato do dono tem o dono.
 */
class VehicleObserver
{
    public function __construct(private readonly VehicleOdometerLog $log) {}

    public function created(Vehicle $vehicle): void
    {
        $this->log->syncRegistration($vehicle);
    }

    public function updated(Vehicle $vehicle): void
    {
        if ($vehicle->wasChanged('odometer_at_registration')) {
            $this->log->syncRegistration($vehicle);
        }
    }
}
