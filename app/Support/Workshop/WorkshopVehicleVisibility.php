<?php

namespace App\Support\Workshop;

use App\Models\Maintenance;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que a oficina pode saber de um veículo que ela só consultou pela placa.
 *
 * A busca da nova OS (/oficina/manutencoes/nova) é aberta a qualquer oficina aprovada (o prefixo
 * /oficina leva o middleware workshop.approved), e aprovação é cadastro conferido, não vínculo com
 * carro nenhum: uma oficina aprovada do outro lado do país não tem mais direito ao histórico deste
 * veículo do que um visitante. Daí o mascaramento de .ai/rules/public-lookup.md: sem vínculo com o
 * veículo, a oficina vê o que a consulta pública mostra (marca, modelo, ano, cor e capa) e nada do
 * que a consulta pública esconde — nunca a placa atual, que nenhuma tela pública mostra
 * (.ai/rules/usergaragepublic.md), nem o hodômetro do veículo, que soma registros ocultos e
 * pendentes que a consulta pública não lista.
 *
 * Vínculo é o mesmo da MaintenancePolicy (.ai/rules/policies.md): a oficina registrou
 * (workshop_id) ou selou (verified_workshop_id) alguma OS deste veículo.
 *
 * O que libera o card sem vínculo é a *posse*, não a falta de dono: a consulta por chassi exige os
 * 17 caracteres exatos, e quem os tem está com o carro ou com o documento na mão — é o fluxo em que
 * a oficina cadastra o veículo. A busca por placa não prova nada: a placa de um carro alheio,
 * inclusive uma antiga, é fácil de obter.
 */
final class WorkshopVehicleVisibility
{
    /**
     * $foundByFullChassis: o veículo veio da consulta exata de 17 caracteres, não da busca por
     * placa. Carro sem dono vinculado continua sendo o carro de alguém — a placa atual a partir de
     * uma antiga e o hodômetro vivo não saem em nenhuma tela pública, e não é a falta de conta do
     * proprietário que os libera para uma oficina que só digitou uma placa.
     */
    public static function showsFullVehicle(?Workshop $workshop, Vehicle $vehicle, bool $foundByFullChassis): bool
    {
        if ($foundByFullChassis) {
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
