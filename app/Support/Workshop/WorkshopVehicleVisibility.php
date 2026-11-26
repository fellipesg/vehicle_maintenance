<?php

namespace App\Support\Workshop;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que a oficina pode saber de um veículo que ela só consultou pela placa.
 *
 * Vale para a etapa 1 (Nova OS e Nova vistoria) e para as três telas de laudo da oficina — lista,
 * detalhe e edição. Elas imprimiam `vehicles.license_plate` direto, e isso é um oráculo de rotação
 * de placa: emitir laudo **não** cria OS, então quem digita uma placa antiga que conhece, emite um
 * laudo e volta à própria lista passaria a ler a placa de hoje daquele carro — e a continuaria lendo
 * depois de o carro ser vendido. O laudo inteiro é do emissor
 * (VehicleInspection::showsFullReportTo); o veículo de terceiro segue esta régua.
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

        return self::linkedVehicleIds($workshop, [$vehicle->id]) !== [];
    }

    /**
     * Quais destes veículos a oficina tem vínculo com, em **uma** consulta.
     *
     * Existe para a lista de laudos (/oficina/vistorias): perguntar veículo por veículo seria uma
     * consulta por linha, e a resposta tem de ser a mesma de `showsFullVehicle` — daí ser este o
     * único lugar onde o SQL do vínculo mora. Não há `$foundByFullChassis` aqui: a posse que libera
     * o card na etapa 1 é a consulta exata de 17 caracteres daquele instante, e nada a registra
     * para depois. Uma lista é sempre o depois.
     *
     * @param  list<int>  $vehicleIds
     * @return list<int>
     */
    /**
     * Quais destes veículos o **leitor** das telas de laudo alcança por inteiro, placa atual
     * incluída, em duas consultas no total.
     *
     * Duas coisas liberam o veículo, e é a união delas: o vínculo de oficina (abaixo) e a posse de
     * hoje — a tenant do leitor ser a dona atual. Só o vínculo esconderia a placa do próprio dono:
     * o laudo que a oficina emitiu sobre carro de terceiro continua na tela dela depois de a tenant
     * dela comprar o carro, estado que `VehicleInspectionPolicy::canChange` já prevê
     * (`DENIED_OWN_VEHICLE`). E só a posse esconderia a placa da oficina que atendeu o carro.
     *
     * É uma pergunta só, usada pela lista e pelo detalhe, porque duas contas separadas é como as
     * duas telas passam a discordar — foi assim que a placa sumiu para o dono na primeira versão
     * disto. O termo de posse é mais estreito que `VehiclePolicy::viewFullHistory` de propósito:
     * nunca mostra mais, e os outros braços daquele gate (admin, consignação do lojista) não
     * alcançam uma rota com `user.type:workshop`.
     *
     * @param  list<int>  $vehicleIds
     * @return list<int>
     */
    public static function fullVehicleIdsFor(?Workshop $workshop, ?User $viewer, array $vehicleIds): array
    {
        return array_values(array_unique(array_merge(
            self::linkedVehicleIds($workshop, $vehicleIds),
            self::ownedVehicleIds($viewer, $vehicleIds),
        )));
    }

    /**
     * O leitor alcança este veículo por inteiro? Mesma conta de `fullVehicleIdsFor`, para uma linha.
     */
    public static function showsFullVehicleTo(?Workshop $workshop, ?User $viewer, Vehicle $vehicle): bool
    {
        return in_array($vehicle->id, self::fullVehicleIdsFor($workshop, $viewer, [$vehicle->id]), true);
    }

    /**
     * Os veículos desta lista de que a tenant do leitor é dona **atual**, em uma consulta. Espelha
     * `VehiclePolicy::viewFullHistory` → `tenantOwnsVehicle`, inclusive o `whereRaw` do booleano.
     *
     * @param  list<int>  $vehicleIds
     * @return list<int>
     */
    private static function ownedVehicleIds(?User $viewer, array $vehicleIds): array
    {
        $vehicleIds = array_values(array_unique(array_filter($vehicleIds)));

        if ($viewer === null || ! $viewer->tenant_id || $vehicleIds === []) {
            return [];
        }

        return $viewer->vehicles()
            ->whereIn('vehicles.id', $vehicleIds)
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->wherePivot('tenant_id', $viewer->tenant_id)
            ->pluck('vehicles.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function linkedVehicleIds(?Workshop $workshop, array $vehicleIds): array
    {
        $vehicleIds = array_values(array_unique(array_filter($vehicleIds)));

        if ($workshop === null || $vehicleIds === []) {
            return [];
        }

        return Maintenance::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where(fn (Builder $query) => $query
                ->where('workshop_id', $workshop->id)
                ->orWhere('verified_workshop_id', $workshop->id))
            ->distinct()
            ->pluck('vehicle_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
