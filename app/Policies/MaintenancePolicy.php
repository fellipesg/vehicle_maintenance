<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MaintenancePolicy
{
    /**
     * Código da recusa quando a manutenção é uma declarada desta conta, mas a conta não é mais a
     * dona atual do veículo (vendeu ou desvinculou). O detalhe da manutenção no portal usa o
     * código para explicar o porquê (Gate::inspect(...)->code()).
     */
    public const DENIED_NOT_CURRENT_OWNER = 'not_current_owner';

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Ver um registro é ver o que a listagem daquele veículo mostraria a esta conta
     * (Maintenance::isVisibleInHistoryTo, o par em memória de Vehicle::maintenancesVisibleTo):
     * ocultada pelo proprietário fica com a oficina autora e com quem ocultou, o resto do histórico
     * anterior só sai com viewFullHistory. A oficina é o caso à parte: ela vê a OS que registrou,
     * em qualquer veículo e mesmo oculta, e nada além dela.
     */
    public function view(User $user, Maintenance $maintenance): bool
    {
        if ($user->isWorkshop() && $user->workshop) {
            return (int) $maintenance->workshop_id === (int) $user->workshop->id;
        }

        return $maintenance->isVisibleInHistoryTo($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Oficina: só a OS com o Selo da própria oficina (verified_at e verified_workshop_id dela). Uma
     * declarada que só cita a oficina (workshop_id) continua de quem declarou. Os demais: só a
     * declarada do próprio tenant e só enquanto forem o dono atual do veículo
     * (VehiclePolicy::addMaintenance); quem vendeu o carro não mexe mais no histórico do comprador.
     * A com Selo da oficina ninguém além da oficina altera. Vale para a API e para a web.
     */
    public function update(User $user, Maintenance $maintenance): Response
    {
        return $this->canChange($user, $maintenance);
    }

    public function delete(User $user, Maintenance $maintenance): Response
    {
        return $this->canChange($user, $maintenance);
    }

    /**
     * Validar (confirmar ou não reconhecer) uma declarada: só a conta da oficina que ela cita,
     * enquanto espera a resposta (WorkshopReviewService).
     */
    public function review(User $user, Maintenance $maintenance): bool
    {
        return $user->isWorkshop()
            && $user->workshop !== null
            && $maintenance->isAwaitingWorkshopReview()
            && (int) $maintenance->workshop_id === (int) $user->workshop->id;
    }

    private function canChange(User $user, Maintenance $maintenance): Response
    {
        if ($user->isWorkshop()) {
            $sealedByThisWorkshop = $user->workshop !== null
                && $maintenance->isVerified()
                && (int) $maintenance->verified_workshop_id === (int) $user->workshop->id;

            return $sealedByThisWorkshop ? Response::allow() : Response::deny();
        }

        if ($maintenance->isVerified() || $maintenance->tenant_id !== $user->tenant_id) {
            return Response::deny();
        }

        $vehicle = $maintenance->vehicle;

        if ($vehicle === null || ! $user->can('addMaintenance', $vehicle)) {
            return Response::deny(code: self::DENIED_NOT_CURRENT_OWNER);
        }

        return Response::allow();
    }
}
