<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;

/**
 * Tirar um registro da consulta pública, e devolvê-lo. É a **única** escrita de
 * `hidden_from_public_at`, e só a equipe chama.
 *
 * Antes era o proprietário que ocultava, com o CRLV-e na mão, pelo fluxo de decisão. A troca é
 * deliberada: o histórico pertence ao chassi e é o ativo do produto, e aquele ocultar apagava
 * exatamente o que o comprador precisa ver — sem sinal nenhum de que algo havia sido suprimido, o
 * que torna "carro sem histórico" indistinguível de "histórico escondido". O que o proprietário tem
 * agora é contestar (MaintenanceOwnerDecisionService::dispute), que marca e não esconde.
 *
 * Esconder continua existindo porque alguns casos pedem: OS que a equipe confirmou ser fraude ou
 * erro de chassi, e pedido de remoção que a empresa decida honrar. A diferença é que passou a ter
 * um humano responsável e um registro de quem decidiu.
 */
class MaintenancePublicVisibilityService
{
    public function hide(Maintenance $maintenance, User $admin, ?string $reason = null): Maintenance
    {
        $maintenance->forceFill([
            'hidden_from_public_at' => $maintenance->hidden_from_public_at ?? now(),
            'hidden_by_user_id' => $admin->id,
            'hidden_reason' => $reason,
        ])->save();

        return $maintenance->fresh();
    }

    public function restore(Maintenance $maintenance): Maintenance
    {
        $maintenance->forceFill([
            'hidden_from_public_at' => null,
            'hidden_by_user_id' => null,
            'hidden_reason' => null,
        ])->save();

        return $maintenance->fresh();
    }
}
