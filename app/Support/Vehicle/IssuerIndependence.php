<?php

namespace App\Support\Vehicle;

use App\Models\UserVehicle;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\Workshop;

/**
 * "Esta oficina é independente deste veículo?" — a pergunta que o Selo da oficina e o laudo de
 * vistoria cautelar precisam responder **sim** antes de existirem.
 *
 * O selo e o laudo são a premissa de confiança do produto: os dois afirmam que **um terceiro**
 * atestou algo sobre o carro ("eu fiz este serviço", "eu examinei e atesto o que encontrei"). Quem
 * atesta o próprio carro não está atestando nada — está se auto-declarando, que é o que a procedência
 * declarada já diz, com o rótulo honesto. É a mesma doutrina que `App\Support\Inspection\InspectionAccess`
 * escreveu para o lojista ("deixá-lo atestar o carro que ele mesmo vende é o conflito de interesse
 * que o laudo existe para resolver"), agora por **dono do veículo** e não só por tipo de papel.
 *
 * ## A guarda falha fechado
 * Qualquer vínculo de posse **atual** do mesmo tenant barra a emissão:
 *
 * - **posse não verificada conta** (`ownership_verified_at` nulo). Verificação é prova de *quem é o
 *   dono*; conflito de interesse é sobre *interesse*, e quem cadastrou o carro na própria conta já
 *   declarou o interesse. Exigir CRLV-e aqui transformaria "não enviei o documento" em licença para
 *   selar o próprio carro — o incentivo exatamente invertido;
 * - **consignação ativa do mesmo tenant conta** (`vehicle_consignments`). Hoje consignação é só de
 *   lojista (`.ai/rules/consignment.md`) e um tenant é de um tipo só, então o cruzamento é
 *   inalcançável; ele entra porque é barato e porque é precisamente o caso que a doutrina do laudo
 *   já nomeia: quem tem o carro para vender não atesta o carro.
 *
 * ## O que esta guarda NÃO cobre
 * Ela compara **tenant**, então não alcança a fraude com duas contas: oficina num e-mail, carro em
 * outro. Ali os `tenant_id` são diferentes e nada aqui enxerga que é a mesma pessoa. Fechar aquilo
 * exigiria cruzar `user_vehicles.owner_document` (encrypted) com o CNPJ da oficina, que é outro
 * trabalho. Ver `.ai/rules/models.md`, "Selo e laudo não saem sobre veículo do próprio tenant".
 */
final class IssuerIndependence
{
    /**
     * A oficina pode atestar sobre este veículo? Oficina desconhecida ou veículo ausente não libera
     * nada: sem os dois lados não há como afirmar independência, e esta pergunta falha fechado.
     */
    public static function workshopMayAttest(?int $workshopId, ?Vehicle $vehicle): bool
    {
        if ($workshopId === null || $vehicle === null) {
            return false;
        }

        $tenantId = Workshop::query()->whereKey($workshopId)->value('tenant_id');

        return ! self::tenantHoldsVehicle($tenantId === null ? null : (int) $tenantId, $vehicle);
    }

    /**
     * Este tenant tem vínculo de posse atual com o veículo — como dono (verificado ou não) ou como
     * loja com consignação ativa?
     *
     * Tenant nulo não "possui" nada: é a OS de oficina em carro sem dono, o caso central do produto
     * (`.ai/rules/models.md`, "O selo não espera o proprietário"), e barrá-lo devolveria a regra que
     * o projeto removeu de propósito.
     */
    public static function tenantHoldsVehicle(?int $tenantId, ?Vehicle $vehicle): bool
    {
        if ($tenantId === null || $vehicle === null || $vehicle->id === null) {
            return false;
        }

        $owns = UserVehicle::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('tenant_id', $tenantId)
            ->where('is_current_owner', true)
            ->exists();

        if ($owns) {
            return true;
        }

        return VehicleConsignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('tenant_id', $tenantId)
            ->where('status', VehicleConsignment::STATUS_ACTIVE)
            ->exists();
    }
}
