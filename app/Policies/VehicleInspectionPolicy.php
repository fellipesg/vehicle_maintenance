<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleInspection;
use App\Support\Inspection\InspectionAccess;
use Illuminate\Auth\Access\Response;

/**
 * Quem emite, lê e corrige um laudo de vistoria cautelar.
 *
 * Emitir é mais restrito que registrar OS, e de propósito: além de ser oficina **aprovada**, a conta
 * precisa da funcionalidade ligada por um admin (App\Features\VehicleInspections). Aprovação diz
 * "esta empresa é quem diz ser"; não diz que ela atesta a condição de um carro de terceiro.
 *
 * Corrigir e excluir é só do emissor, inclusive depois de o proprietário contestar — é ele quem
 * responde pelo que assinou. Ler segue a regra do histórico daquele veículo
 * (VehicleInspection::isVisibleInHistoryTo), o par em memória de Vehicle::inspectionsVisibleTo:
 * autorizar pela rota de detalhe tem de deixar entrar exatamente quem veria o laudo na listagem,
 * senão o lojista em consignação enumera ids e abre o que a listagem esconde dele
 * (.ai/rules/policies.md).
 */
class VehicleInspectionPolicy
{
    /**
     * Código da recusa quando a conta é oficina aprovada mas não tem a funcionalidade ligada. A
     * tela lê o código com Gate::inspect para explicar em vez de devolver um 403 seco.
     */
    public const DENIED_FEATURE_OFF = 'inspections_feature_off';

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, VehicleInspection $inspection): bool
    {
        if ($user->isWorkshop() && $user->workshop) {
            return $inspection->isIssuedByWorkshopId($user->workshop->id);
        }

        return $inspection->isVisibleInHistoryTo($user);
    }

    public function create(User $user): Response
    {
        if (! $user->isWorkshop() || $user->workshop === null || ! $user->workshop->isApproved()) {
            return Response::deny();
        }

        if (! InspectionAccess::allowsIssuing($user)) {
            return Response::deny(code: self::DENIED_FEATURE_OFF);
        }

        return Response::allow();
    }

    public function update(User $user, VehicleInspection $inspection): Response
    {
        return $this->canChange($user, $inspection);
    }

    public function delete(User $user, VehicleInspection $inspection): Response
    {
        return $this->canChange($user, $inspection);
    }

    private function canChange(User $user, VehicleInspection $inspection): Response
    {
        if (! $user->isWorkshop() || $user->workshop === null) {
            return Response::deny();
        }

        if (! $inspection->isIssuedByWorkshopId($user->workshop->id)) {
            return Response::deny();
        }

        if (! InspectionAccess::allowsIssuing($user)) {
            return Response::deny(code: self::DENIED_FEATURE_OFF);
        }

        return Response::allow();
    }
}
