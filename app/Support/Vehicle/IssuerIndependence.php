<?php

namespace App\Support\Vehicle;

use App\Models\UserVehicle;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;

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
 *   já nomeia: quem tem o carro para vender não atesta o carro;
 * - **a oficina sem tenant é comparada pelo usuário dela**. `workshops.tenant_id` é nulo quando a
 *   conta nasceu sem tenant ou quando o tenant foi apagado (`nullOnDelete`), e comparar nulo com
 *   nulo não acha nada: a guarda desligaria em silêncio justamente para a conta de cadastro torto.
 *   Então o lado da oficina são **os dois** identificadores, tenant e `user_id`, e basta um casar
 *   com a posse para barrar. Em dados sãos o `user_id` é redundante (um usuário tem um tenant só),
 *   e é por isso que incluí-lo não aperta nada no caso central.
 *
 * ## O que esta guarda NÃO cobre
 * Ela compara **tenant** (e o usuário da própria oficina), então não alcança a fraude com duas
 * contas: oficina num e-mail, carro em outro. Ali os `tenant_id` são diferentes, os `user_id`
 * também, e nada aqui enxerga que é a mesma pessoa. Fechar aquilo exigiria cruzar
 * `user_vehicles.owner_document` (encrypted) com o CNPJ da oficina, que é outro trabalho. Ver
 * `.ai/rules/models.md`, "Selo e laudo não saem sobre veículo do próprio tenant".
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

        $workshop = Workshop::query()->whereKey($workshopId)->first(['id', 'tenant_id', 'user_id']);

        if ($workshop === null) {
            return false;
        }

        return ! self::holderHoldsVehicle(
            $workshop->tenant_id === null ? null : (int) $workshop->tenant_id,
            $workshop->user_id === null ? null : (int) $workshop->user_id,
            $vehicle,
        );
    }

    /**
     * Este tenant tem vínculo de posse atual com o veículo — como dono (verificado ou não) ou como
     * loja com consignação ativa?
     *
     * Tenant nulo não "possui" nada: é a OS de oficina em carro sem dono, o caso central do produto
     * (`.ai/rules/models.md`, "O selo não espera o proprietário"), e barrá-lo devolveria a regra que
     * o projeto removeu de propósito. Quem pergunta pela oficina usa `workshopMayAttest`, que não
     * depende só do tenant.
     */
    public static function tenantHoldsVehicle(?int $tenantId, ?Vehicle $vehicle): bool
    {
        return self::holderHoldsVehicle($tenantId, null, $vehicle);
    }

    /**
     * A posse atual do veículo é de quem estes identificadores descrevem? Tenant **ou** usuário:
     * identificador nulo é o que não se sabe, e o que não se sabe não entra na comparação — nulo
     * casando com nulo transformaria cadastro torto em licença para selar o próprio carro. Sem
     * nenhum dos dois não há pergunta a fazer, e a resposta é "não possui".
     */
    private static function holderHoldsVehicle(?int $tenantId, ?int $userId, ?Vehicle $vehicle): bool
    {
        if ($vehicle === null || $vehicle->id === null) {
            return false;
        }

        if ($tenantId === null && $userId === null) {
            return false;
        }

        $owns = UserVehicle::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_current_owner', true)
            ->where(fn (Builder $query) => self::matchHolder($query, 'tenant_id', $tenantId, 'user_id', $userId))
            ->exists();

        if ($owns) {
            return true;
        }

        return VehicleConsignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', VehicleConsignment::STATUS_ACTIVE)
            ->where(fn (Builder $query) => self::matchHolder($query, 'tenant_id', $tenantId, 'garage_user_id', $userId))
            ->exists();
    }

    /**
     * O grupo "é deste tenant ou deste usuário", pulando o identificador que não se conhece.
     */
    private static function matchHolder(
        Builder $query,
        string $tenantColumn,
        ?int $tenantId,
        string $userColumn,
        ?int $userId,
    ): void {
        if ($tenantId !== null) {
            $query->orWhere($tenantColumn, $tenantId);
        }

        if ($userId !== null) {
            $query->orWhere($userColumn, $userId);
        }
    }
}
