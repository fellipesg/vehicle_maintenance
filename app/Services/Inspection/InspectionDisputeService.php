<?php

namespace App\Services\Inspection;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Notifications\VehicleInspectionDisputedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * A resposta do proprietário a um laudo sobre o carro dele. É a **única** escrita de
 * `owner_disputed_at`, `owner_disputed_by_user_id` e `owner_dispute_reason` — as três não são
 * fillable, como o veredito e os achados, justamente para que nenhum `update()` de controller
 * escreva contestação no laudo de outra pessoa.
 *
 * Até aqui o laudo era a única afirmação do produto que ninguém podia responder: a oficina emitia,
 * as telas já desenhavam "Contestado pelo proprietário" e o campo nunca era escrito fora de teste.
 * Um laudo afirma a condição do carro de alguém, com veredito e eixo reprovado; publicar isso sem
 * direito de resposta é o que a contestação existe para impedir — e é por isso que o recurso de API
 * esperava este serviço (.ai/rules/inspections.md).
 *
 * **Contestar marca, não esconde.** O laudo contestado continua em todas as superfícies, com a marca
 * à vista: quem compra vê o laudo *e* vê que o dono discorda, e decide. Sumir com ele seria a mesma
 * alavanca que o produto tirou da mão do proprietário nas manutenções (ver "O proprietário contesta;
 * esconder é da equipe" em .ai/rules/models.md). Esconder de verdade ficou com a equipe, depois de
 * analisar (App\Services\Inspection\InspectionPublicVisibilityService).
 *
 * **Não pede CRLV-e**, como a contestação de OS: é a ação que o dono atual tem sem o documento.
 * Exigir a verificação de posse aqui calaria justamente quem acabou de comprar o carro.
 *
 * **E não mexe no hodômetro.** O irmão desta classe
 * (App\Services\Maintenance\MaintenanceOwnerDecisionService) recalcula o `current_kilometers` ao
 * contestar, porque a OS contestada sai da conta do hodômetro. Aqui não há conta de onde sair: a
 * leitura da vistoria nunca alimentou `vehicles.current_kilometers`, e isso é permanente
 * (.ai/rules/inspections.md, "O hodômetro da vistoria não move o do veículo"). Chamar o
 * VehicleMileageService daqui por simetria seria a porta dos fundos dessa regra.
 */
final class InspectionDisputeService
{
    /**
     * Laudo que o leitor não alcança: suprimido pela equipe. Contestar o que não se vê não faz
     * sentido, e responder "não existe" é o mesmo que a listagem já responde.
     */
    public const NOT_DISPUTABLE_MESSAGE = 'Este laudo não está mais disponível para contestação.';

    public const NOT_OWNER_MESSAGE = 'Você não é o proprietário atual deste veículo.';

    /**
     * Resultado da pergunta "é o dono atual?" por conta e veículo, dentro da mesma instância.
     *
     * A aba Vistorias pergunta uma vez por card, e vários laudos do mesmo veículo repetiriam a
     * mesma consulta de policy — o mesmo memo de MaintenanceOwnerDecisionService::isCurrentOwner.
     *
     * @var array<string, bool>
     */
    private array $currentOwnerCache = [];

    /**
     * Quem pode contestar: o dono atual do veículo (VehiclePolicy::update), e só enquanto o laudo
     * está de pé para ele. É o que a tela pergunta para desenhar o botão, e o que o serviço confere
     * de novo ao gravar — a tela não autoriza nada.
     */
    public function canDispute(User $owner, VehicleInspection $inspection): bool
    {
        $vehicle = $this->vehicleOf($inspection);

        return $vehicle !== null
            && ! $inspection->hiddenFrom($owner)
            && $this->isCurrentOwner($owner, $vehicle);
    }

    public function dispute(VehicleInspection $inspection, User $owner, ?string $reason = null): VehicleInspection
    {
        $this->assertCurrentOwner($inspection, $owner);

        if ($inspection->hiddenFrom($owner)) {
            throw ValidationException::withMessages(['dispute' => self::NOT_DISPUTABLE_MESSAGE]);
        }

        // A gravação é idempotente na data, mas o aviso precisava ser também: cada POST mandava
        // **outro** e-mail para a caixa da oficina, com 500 caracteres livres do formulário, e o
        // dono atual podia repetir à vontade. Avisa na primeira contestação e quando o motivo
        // realmente muda — a segunda é informação nova para quem corrige o laudo; reenviar o mesmo
        // texto não é.
        $isFirstDispute = $inspection->owner_disputed_at === null;
        $reasonChanged = $inspection->owner_dispute_reason !== $reason;

        // Contestar de novo não reabre a data: a primeira é quando o dono discordou, e sobrescrevê-la
        // apagaria há quanto tempo a oficina está sem responder. O motivo, esse, é o mais recente.
        $inspection->forceFill([
            'owner_disputed_at' => $inspection->owner_disputed_at ?? now(),
            'owner_disputed_by_user_id' => $owner->id,
            'owner_dispute_reason' => $reason,
        ])->save();

        $inspection = $inspection->fresh();

        // A oficina emissora é a única que pode corrigir ou excluir o laudo (VehicleInspectionPolicy),
        // então sem este aviso a contestação ficaria invisível para quem resolve.
        if ($isFirstDispute || $reasonChanged) {
            $inspection->loadMissing('workshop.user');
            $inspection->getRelationValue('workshop')?->user
                ?->notify(new VehicleInspectionDisputedNotification($inspection, $reason));
        }

        return $inspection;
    }

    /**
     * Retirar a contestação é do mesmo dono, e apaga as três colunas: o laudo volta a ser só o que a
     * oficina assinou. Não devolve nada que a supressão tenha tirado — as duas coisas são
     * independentes de propósito.
     *
     * Confere `hiddenFrom` como `dispute`: laudo suprimido pela equipe não é contestável, e também
     * não é **descontestável**. Sem esta linha a assimetria era real — o dono não podia contestar o
     * que não vê, mas podia apagar a contestação que já estava lá, mexendo num documento que a
     * equipe tirou de circulação justamente porque está sob análise. Quando o laudo voltar ao
     * histórico, retirar volta a funcionar.
     */
    public function withdrawDispute(VehicleInspection $inspection, User $owner): VehicleInspection
    {
        $this->assertCurrentOwner($inspection, $owner);

        if ($inspection->hiddenFrom($owner)) {
            throw ValidationException::withMessages(['dispute' => self::NOT_DISPUTABLE_MESSAGE]);
        }

        $inspection->forceFill([
            'owner_disputed_at' => null,
            'owner_disputed_by_user_id' => null,
            'owner_dispute_reason' => null,
        ])->save();

        return $inspection->fresh();
    }

    private function assertCurrentOwner(VehicleInspection $inspection, User $owner): void
    {
        $vehicle = $this->vehicleOf($inspection);

        if ($vehicle === null || ! $this->isCurrentOwner($owner, $vehicle)) {
            throw new AuthorizationException(self::NOT_OWNER_MESSAGE);
        }
    }

    /**
     * loadMissing, não $inspection->vehicle: com preventLazyLoading fora de produção a autorização
     * quebraria em vez de responder (.ai/rules/policies.md).
     */
    private function vehicleOf(VehicleInspection $inspection): ?Vehicle
    {
        $inspection->loadMissing('vehicle');

        $vehicle = $inspection->getRelationValue('vehicle');

        return $vehicle instanceof Vehicle ? $vehicle : null;
    }

    private function isCurrentOwner(User $owner, Vehicle $vehicle): bool
    {
        return $this->currentOwnerCache[$owner->id.':'.$vehicle->id] ??= $owner->can('update', $vehicle);
    }
}
