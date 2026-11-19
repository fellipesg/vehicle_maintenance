<?php

namespace App\Services\Inspection;

use App\Models\User;
use App\Models\VehicleInspection;

/**
 * Tirar um laudo de circulação, e devolvê-lo. É a **única** escrita de `hidden_from_public_at`, e só
 * a equipe chama — o irmão de App\Services\Maintenance\MaintenancePublicVisibilityService, com a
 * mesma divisão: o proprietário contesta (InspectionDisputeService), a equipe suprime.
 *
 * A divisão não é formalidade. Suprimir apaga da vista do comprador justamente o que ele precisa ver,
 * e sem sinal nenhum — "carro sem laudo" fica indistinguível de "laudo suprimido". Na mão de quem
 * tem interesse em esconder, vira a alavanca errada. Então tem nome (`hidden_by_user_id`), motivo
 * (`hidden_reason`) e um humano responsável, e serve para o que não tem outra saída: laudo de chassi
 * trocado, fraude confirmada, pedido de remoção que a empresa decida honrar.
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
    public function hide(VehicleInspection $inspection, User $admin, ?string $reason = null): VehicleInspection
    {
        $inspection->forceFill([
            'hidden_from_public_at' => $inspection->hidden_from_public_at ?? now(),
            'hidden_by_user_id' => $admin->id,
            'hidden_reason' => $reason,
        ])->save();

        return $inspection->fresh();
    }

    public function restore(VehicleInspection $inspection): VehicleInspection
    {
        $inspection->forceFill([
            'hidden_from_public_at' => null,
            'hidden_by_user_id' => null,
            'hidden_reason' => null,
        ])->save();

        return $inspection->fresh();
    }
}
