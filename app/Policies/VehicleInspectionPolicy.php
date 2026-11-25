<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
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

    /**
     * Código da recusa por conflito de interesse: o veículo é do tenant da própria oficina. Separado
     * de DENIED_FEATURE_OFF porque a resposta é outra — não é "pede para o admin ligar", é "este
     * carro é seu, e laudo sobre o próprio carro não é laudo".
     */
    public const DENIED_OWN_VEHICLE = 'inspections_own_vehicle';

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

    /**
     * Abrir o PDF do laudo. **Não** é a mesma pergunta de `view`: a forma mínima
     * (App\Support\Inspection\InspectionRedactor) tira o PDF, o resumo e as observações de quem não
     * tem o laudo inteiro, e uma rota de arquivo mais frouxa que a tela devolveria por download o
     * que a tela acabou de esconder. Então a resposta é a mesma de
     * VehicleInspection::showsFullReportTo, que a redação também usa.
     */
    public function viewReport(User $user, VehicleInspection $inspection): bool
    {
        return $inspection->showsFullReportTo($user);
    }

    /**
     * `$vehicle` é opcional porque a pergunta aparece nas duas formas: sem veículo (item de menu,
     * botão "Nova vistoria", a tela de criação antes da etapa 1) e sobre um veículo já resolvido, no
     * envio do formulário. Só a segunda pode responder o conflito de interesse, e é a que decide se
     * o laudo nasce — a primeira continua valendo o que valia.
     */
    public function create(User $user, ?Vehicle $vehicle = null): Response
    {
        if (! $user->isWorkshop() || $user->workshop === null || ! $user->workshop->isApproved()) {
            return Response::deny();
        }

        if (! InspectionAccess::allowsIssuing($user)) {
            return Response::deny(code: self::DENIED_FEATURE_OFF);
        }

        if (! InspectionAccess::allowsIssuingAbout($user, $vehicle)) {
            return Response::deny(code: self::DENIED_OWN_VEHICLE);
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

    /**
     * Corrigir e excluir é do emissor — menos quando o carro passou a ser do tenant dele. Aí as duas
     * ações viram a fraude que o laudo existe para impedir: editar reescreve o veredito do carro que
     * ele está vendendo, e excluir apaga o "Reprovado" que o comprador precisava ler. O laudo não
     * fica refém: suprimir é da equipe (`/admin/vistorias`), como manda .ai/rules/inspections.md, e é
     * por lá que um laudo intocável se resolve. O preço é a oficina não arrumar nem a data depois de
     * comprar o carro, e é o preço certo.
     */
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

        $inspection->loadMissing('vehicle');

        if (! InspectionAccess::allowsIssuingAbout($user, $inspection->getRelationValue('vehicle'))) {
            return Response::deny(code: self::DENIED_OWN_VEHICLE);
        }

        return Response::allow();
    }
}
