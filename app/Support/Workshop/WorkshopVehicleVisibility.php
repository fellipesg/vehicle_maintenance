<?php

namespace App\Support\Workshop;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que a oficina pode saber de um veículo que ela só consultou pela placa.
 *
 * A busca da nova OS (/oficina/manutencoes/nova) é aberta a qualquer conta de oficina, que nasce por
 * autocadastro, então ela segue o mascaramento de .ai/rules/public-lookup.md: sem vínculo com o
 * veículo, a oficina vê o que a consulta pública mostra (marca, modelo, ano, cor e capa) e nada do
 * que a consulta pública esconde — nunca a placa atual, que nenhuma tela pública mostra
 * (.ai/rules/usergaragepublic.md), nem o hodômetro do veículo, que soma registros ocultos e
 * pendentes que a consulta pública não lista.
 *
 * Vínculo é o mesmo da MaintenancePolicy (.ai/rules/policies.md): a oficina registrou
 * (workshop_id) ou selou (verified_workshop_id) alguma OS deste veículo. Veículo sem dono atual não
 * tem o que proteger — é o caso do carro que a própria oficina cadastra pelo chassi.
 */
final class WorkshopVehicleVisibility
{
    public static function showsFullVehicle(?Workshop $workshop, Vehicle $vehicle, bool $vehicleHasOwner): bool
    {
        if (! $vehicleHasOwner) {
            return true;
        }

        if ($workshop === null) {
            return false;
        }

        return Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->where(fn (Builder $query) => $query
                ->where('workshop_id', $workshop->id)
                ->orWhere('verified_workshop_id', $workshop->id))
            ->exists();
    }
}
