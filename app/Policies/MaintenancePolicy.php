<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;
use App\Support\Vehicle\IssuerIndependence;
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
     * Oficina: só a OS que ela mesma registrou (Maintenance::isAuthoredByWorkshopId — com selo é o
     * verified_workshop_id; sem selo, a que nasceu pendente num carro de terceiro). Uma declarada
     * que só cita a oficina (workshop_id) continua de quem declarou. Os demais: só a
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

    /**
     * A metade da fila que **emite o Selo da oficina**, e por isso a única que responde ao invariante
     * de independência (App\Support\Vehicle\IssuerIndependence): a oficina não confirma serviço em
     * veículo do tenant dela. Sem isso, a conta declarava o serviço do próprio carro por um lado e o
     * confirmava pelo outro — o selo pelo caminho mais longo.
     *
     * **Não reconhecer fica em `review`, de propósito.** Rejeitar não emite nada: tira o vínculo com
     * a oficina. Negar as duas respostas junto prenderia o pedido na fila para sempre, porque o carro
     * pode virar do tenant da oficina **depois** do pedido — `Maintenance::scopeAwaitingReviewBy`
     * continua listando (status e workshop_id, nada de posse) e `WorkshopReviewService` só limpa um
     * pendente quando a declarada é regravada, o que uma mudança de posse não faz. Rejeitar é a saída,
     * e é a resposta honesta de quem não pode atestar.
     */
    public function confirmReview(User $user, Maintenance $maintenance): bool
    {
        return $this->review($user, $maintenance)
            && IssuerIndependence::workshopMayAttest(
                (int) $user->workshop->id,
                $maintenance->loadMissing('vehicle')->getRelationValue('vehicle'),
            );
    }

    private function canChange(User $user, Maintenance $maintenance): Response
    {
        if ($user->isWorkshop()) {
            return $maintenance->isAuthoredByWorkshopId($user->workshop?->id)
                ? Response::allow()
                : Response::deny();
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
