<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
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
