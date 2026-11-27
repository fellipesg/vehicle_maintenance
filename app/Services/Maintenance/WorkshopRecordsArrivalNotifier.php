<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Notifications\VehicleInspectionIssuedNotification;
use App\Notifications\WorkshopRecordRegisteredNotification;
use App\Notifications\WorkshopRecordsPendingNotification;
use App\Services\FcmService;

/**
 * Quando um proprietário passa a ter um veículo com registros de oficina pendentes: notificação no
 * sino, e-mail e push (FCM). Falha do Firebase não derruba o vínculo.
 */
class WorkshopRecordsArrivalNotifier
{
    public function __construct(private readonly OwnerlessMaintenanceService $ownerless) {}

    /**
     * Avisa o dono atual de que uma oficina acabou de registrar um serviço no veículo dele. O
     * registro já entrou no histórico com o selo: o aviso existe para ele saber e poder contestar,
     * não para autorizar. Carro sem dono não tem a quem avisar — ali o caminho é o convite da
     * oficina (MaintenanceInviteService) e, quando o dono chega, o notify() abaixo.
     */
    public function notifyOwnerOfWorkshopRecord(Maintenance $maintenance): void
    {
        // loadMissing, não $maintenance->vehicle: com preventLazyLoading fora de produção o aviso
        // quebraria a gravação da OS em vez de sair.
        $maintenance->loadMissing('vehicle');
        $vehicle = $maintenance->getRelationValue('vehicle');

        if (! $vehicle instanceof Vehicle) {
            return;
        }

        $owner = $vehicle->currentOwner();

        if ($owner === null || $owner->isGarage()) {
            return;
        }

        $notification = new WorkshopRecordRegisteredNotification($maintenance);
        $owner->notify($notification);

        try {
            app(FcmService::class)->sendToUser($owner->id, $notification->title(), $notification->body(), [
                'type' => WorkshopRecordRegisteredNotification::TYPE,
                'maintenance_id' => (string) $maintenance->id,
                'vehicle_id' => (string) $vehicle->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Avisa o dono atual de que uma oficina acabou de emitir um laudo de vistoria cautelar sobre o
     * veículo dele. Mesma forma do aviso de OS acima, e de propósito **aqui** e não num segundo
     * mecanismo: a política de quem recebe aviso de registro de oficina (dono atual, nunca conta de
     * lojista, push com try/catch, loadMissing em vez de lazy load) é uma só, e duas cópias dela é
     * exatamente como uma das duas esquece a linha da conta de lojista.
     *
     * A alavanca de resposta já existia (InspectionDisputeService, PR #78); faltava a de
     * descoberta — sem este aviso o dono só achava um "Reprovado" sobre o próprio carro se abrisse
     * a aba Vistorias por conta própria.
     *
     * **Não confere workshop_message_opt_outs** (App\Support\Workshop\WorkshopMessageConsent), pelo
     * mesmo motivo do aviso de OS, que também não confere: aquele descadastro é das mensagens que a
     * **oficina escreve e dispara** ao cliente (WorkshopFollowUpNotification e o push de
     * WorkshopMessageTemplateDispatcher — "quem pede para parar de receber não está pedindo para
     * trocar de canal"). Este aviso não é da oficina: é o produto informando o proprietário de que
     * alguém afirmou algo sobre o bem dele, e é a única porta de entrada para o direito de resposta.
     * Calá-lo por um opt-out de marketing de uma oficina qualquer tiraria em silêncio a chance de
     * contestar um laudo — o oposto do que o descadastro quer dizer. Quem escolhe não receber avisos
     * do produto tem a conta e o sino; o canal de e-mail transacional segue a supressão permanente
     * de email_suppressions, como todo o resto.
     */
    public function notifyOwnerOfInspection(VehicleInspection $inspection): void
    {
        // loadMissing, não $inspection->vehicle: com preventLazyLoading fora de produção o aviso
        // quebraria a emissão do laudo em vez de sair.
        $inspection->loadMissing('vehicle');
        $vehicle = $inspection->getRelationValue('vehicle');

        if (! $vehicle instanceof Vehicle) {
            return;
        }

        $owner = $vehicle->currentOwner();

        if ($owner === null || $owner->isGarage()) {
            return;
        }

        $notification = new VehicleInspectionIssuedNotification($inspection);
        $owner->notify($notification);

        try {
            app(FcmService::class)->sendToUser($owner->id, $notification->title(), $notification->body(), [
                'type' => VehicleInspectionIssuedNotification::TYPE,
                'inspection_id' => (string) $inspection->id,
                'vehicle_id' => (string) $vehicle->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function notify(User $owner, Vehicle $vehicle): void
    {
        if ($owner->isGarage()) {
            return;
        }

        $count = $this->ownerless->pendingCountForVehicle($vehicle);

        if ($count === 0) {
            return;
        }

        $notification = new WorkshopRecordsPendingNotification($vehicle, $count);
        $owner->notify($notification);

        try {
            app(FcmService::class)->sendToUser($owner->id, $notification->title(), $notification->body(), [
                'type' => WorkshopRecordsPendingNotification::TYPE,
                'vehicle_id' => (string) $vehicle->id,
                'count' => (string) $count,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
