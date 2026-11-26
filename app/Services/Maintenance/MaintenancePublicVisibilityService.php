<?php

namespace App\Services\Maintenance;

use App\Enums\VisibilityEventAction;
use App\Models\Maintenance;
use App\Models\MaintenanceVisibilityEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMileageService;
use Illuminate\Support\Facades\DB;

/**
 * Tirar um registro da consulta pública, e devolvê-lo. É a **única** escrita de
 * `hidden_from_public_at` e da trilha `maintenance_visibility_events`, e só a equipe chama.
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
 *
 * **E uma trilha, pelo mesmo motivo.** As três colunas são o interruptor do estado corrente:
 * devolver o registro zera as três, então sozinhas elas não sustentam o responsável — depois de
 * republicar, ninguém descobriria que a OS esteve fora do ar, quem a tirou nem por quê. Cada
 * supressão e cada devolução gravam um App\Models\MaintenanceVisibilityEvent (ator, motivo,
 * horário), append-only, lido pela tela do admin. Idêntico ao irmão
 * (App\Services\Inspection\InspectionPublicVisibilityService), que é como a equipe espera ler os
 * dois.
 *
 * Estado, trilha e hodômetro vão na **mesma transação**: trilha que pode divergir do estado é pior
 * que trilha nenhuma, porque passa a ser consultada como prova.
 */
class MaintenancePublicVisibilityService
{
    /**
     * Suprimir grava evento **sempre**, inclusive sobre uma OS já suprimida: ali a data da primeira
     * supressão é preservada (é quando a OS saiu do ar), mas o ato é novo — outro admin, outro
     * motivo — e é o ato que a trilha registra.
     */
    public function hide(Maintenance $maintenance, User $admin, ?string $reason = null): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $admin, $reason): Maintenance {
            $maintenance->forceFill([
                'hidden_from_public_at' => $maintenance->hidden_from_public_at ?? now(),
                'hidden_by_user_id' => $admin->id,
                'hidden_reason' => $reason,
            ])->save();

            $this->record($maintenance, VisibilityEventAction::Hidden, $admin, $reason);

            $maintenance = $maintenance->fresh();
            $this->refreshOdometer($maintenance);

            return $maintenance;
        });
    }

    /**
     * Devolver zera as três colunas de estado, e aqui isso é **obrigatório**, não só coerente:
     * `Maintenance::scopeCountsTowardOdometer` lê `hidden_by_user_id` para distinguir a supressão da
     * equipe do ocultar antigo do proprietário, então deixar o nome preso ali tiraria a OS da conta
     * do hodômetro para sempre (.ai/rules/models.md) — e a projeção de leituras lê a mesma coluna,
     * pelo MaintenanceObserver, então o nome preso travaria `counts_toward_odometer` também. O que a
     * supressão foi fica na trilha, que esta devolução acrescenta em vez de apagar.
     *
     * Exige o admin por isso: devolver o ativo à circulação é decisão de gente, e trilha sem nome
     * não serve de prova. OS que não estava suprimida **não** gera evento: não houve transição, e
     * inventá-la encheria a trilha de atos que não aconteceram — é também o caso das linhas do fluxo
     * antigo, ocultadas pelo proprietário, que a migração não semeou.
     *
     * A transição é lida com a linha **travada**, como no irmão do laudo: sem a trava, duas
     * devoluções simultâneas da mesma OS gravariam duas linhas de trilha para uma transição só, e a
     * trilha é append-only. Ver InspectionPublicVisibilityService::restore.
     */
    public function restore(Maintenance $maintenance, User $admin): Maintenance
    {
        return DB::transaction(function () use ($maintenance, $admin): Maintenance {
            $wasHidden = Maintenance::query()
                ->lockForUpdate()
                ->find($maintenance->id)
                ?->isHiddenFromPublic() ?? false;

            $maintenance->forceFill([
                'hidden_from_public_at' => null,
                'hidden_by_user_id' => null,
                'hidden_reason' => null,
            ])->save();

            if ($wasHidden) {
                $this->record($maintenance, VisibilityEventAction::Restored, $admin);
            }

            $maintenance = $maintenance->fresh();
            $this->refreshOdometer($maintenance);

            return $maintenance;
        });
    }

    /**
     * `occurred_at` é gravado aqui, e não deixado para o `created_at`, porque a trilha também
     * carrega eventos semeados pela migração com o horário original da supressão — as duas colunas
     * divergem de propósito nessas linhas.
     */
    private function record(
        Maintenance $maintenance,
        VisibilityEventAction $action,
        User $admin,
        ?string $reason = null,
    ): void {
        MaintenanceVisibilityEvent::create([
            'maintenance_id' => $maintenance->id,
            'action' => $action,
            'actor_user_id' => $admin->id,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Tirar de circulação inclui o número: o registro sai da conta do hodômetro
     * (Maintenance::scopeCountsTowardOdometer), então o current_kilometers precisa ser recalculado
     * na hora — senão o veículo fica exibindo o km de um registro que ninguém mais vê, e o piso de
     * plausibilidade continua recusando OS nova. Vale nos dois sentidos: republicar devolve.
     *
     * É a assimetria legítima com o irmão do laudo: a leitura da vistoria nunca alimentou
     * `vehicles.current_kilometers`, então lá não há conta de onde sair (.ai/rules/inspections.md).
     */
    private function refreshOdometer(Maintenance $maintenance): void
    {
        $maintenance->loadMissing('vehicle');
        $vehicle = $maintenance->getRelationValue('vehicle');

        if ($vehicle instanceof Vehicle) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle);
        }
    }
}
