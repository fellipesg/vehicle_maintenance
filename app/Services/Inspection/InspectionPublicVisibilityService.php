<?php

namespace App\Services\Inspection;

use App\Enums\VisibilityEventAction;
use App\Models\InspectionVisibilityEvent;
use App\Models\User;
use App\Models\VehicleInspection;
use Illuminate\Support\Facades\DB;

/**
 * Tirar um laudo de circulação, e devolvê-lo. É a **única** escrita de `hidden_from_public_at` e da
 * trilha `inspection_visibility_events`, e só a equipe chama — o irmão de
 * App\Services\Maintenance\MaintenancePublicVisibilityService, com a mesma divisão: o proprietário
 * contesta (InspectionDisputeService), a equipe suprime.
 *
 * A divisão não é formalidade. Suprimir apaga da vista do comprador justamente o que ele precisa ver,
 * e sem sinal nenhum — "carro sem laudo" fica indistinguível de "laudo suprimido". Na mão de quem
 * tem interesse em esconder, vira a alavanca errada. Então tem nome (`hidden_by_user_id`), motivo
 * (`hidden_reason`) e um humano responsável, e serve para o que não tem outra saída: laudo de chassi
 * trocado, fraude confirmada, pedido de remoção que a empresa decida honrar.
 *
 * **E tem trilha.** As três colunas são o interruptor do estado corrente: devolver o laudo zera as
 * três, então por si só elas não sustentam a exigência de responsável que o parágrafo acima
 * descreve — depois de republicar, ninguém descobriria que o laudo esteve fora do ar, quem o tirou
 * nem com que justificativa. Cada supressão e cada devolução gravam um
 * App\Models\InspectionVisibilityEvent (ator, motivo, horário), append-only, lido pela tela do
 * admin. O histórico é uma sequência, não um par de campos.
 *
 * Estado e evento vão na **mesma transação**: trilha que pode divergir do estado é pior que trilha
 * nenhuma, porque passa a ser consultada como prova.
 *
 * Suprimir **não** retira a contestação, e contestar não suprime: as colunas são independentes de
 * propósito, para que republicar um laudo não apague em silêncio que o dono discordou dele.
 *
 * Quem continua vendo o laudo suprimido é a oficina emissora (VehicleInspection::hiddenFrom) — é ela
 * quem corrige o que assinou — e as telas de admin, que consultam direto e não passam pela redação.
 *
 * Diferente do irmão, aqui não há hodômetro para recalcular: a leitura da vistoria nunca alimentou
 * `vehicles.current_kilometers` (.ai/rules/inspections.md).
 */
final class InspectionPublicVisibilityService
{
    /**
     * Suprimir grava evento **sempre**, inclusive sobre um laudo já suprimido: ali a data da
     * primeira supressão é preservada (é quando o laudo saiu do ar), mas o ato é novo — outro admin,
     * outro motivo — e é o ato que a trilha registra.
     */
    public function hide(VehicleInspection $inspection, User $admin, ?string $reason = null): VehicleInspection
    {
        return DB::transaction(function () use ($inspection, $admin, $reason): VehicleInspection {
            $inspection->forceFill([
                'hidden_from_public_at' => $inspection->hidden_from_public_at ?? now(),
                'hidden_by_user_id' => $admin->id,
                'hidden_reason' => $reason,
            ])->save();

            $this->record($inspection, VisibilityEventAction::Hidden, $admin, $reason);

            return $inspection->fresh();
        });
    }

    /**
     * Devolver zera as três colunas de estado — elas descrevem a supressão **corrente**, e
     * `hidden_by_user_id` preso ali depois de republicar afirmaria que o laudo segue suprimido. O
     * que a supressão foi fica na trilha, que esta devolução acrescenta em vez de apagar.
     *
     * Exige o admin por isso: devolver o ativo à circulação é decisão de gente, e trilha sem nome
     * não serve de prova. Laudo que não estava suprimido **não** gera evento: não houve transição, e
     * inventá-la encheria a trilha de atos que não aconteceram.
     */
    public function restore(VehicleInspection $inspection, User $admin): VehicleInspection
    {
        return DB::transaction(function () use ($inspection, $admin): VehicleInspection {
            $wasHidden = $inspection->isHiddenFromPublic();

            $inspection->forceFill([
                'hidden_from_public_at' => null,
                'hidden_by_user_id' => null,
                'hidden_reason' => null,
            ])->save();

            if ($wasHidden) {
                $this->record($inspection, VisibilityEventAction::Restored, $admin);
            }

            return $inspection->fresh();
        });
    }

    /**
     * `occurred_at` é gravado aqui, e não deixado para o `created_at`, porque a trilha também
     * carrega eventos semeados pela migração com o horário original da supressão — as duas colunas
     * divergem de propósito nessas linhas.
     */
    private function record(
        VehicleInspection $inspection,
        VisibilityEventAction $action,
        User $admin,
        ?string $reason = null,
    ): void {
        InspectionVisibilityEvent::create([
            'vehicle_inspection_id' => $inspection->id,
            'action' => $action,
            'actor_user_id' => $admin->id,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
