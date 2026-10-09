<?php

namespace App\Services\Maintenance;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\WorkshopRecordsPendingNotification;
use App\Services\FcmService;

/**
 * Quando um proprietário passa a ter um veículo com registros de oficina pendentes: notificação no
 * sino, e-mail e push (FCM). Falha do Firebase não derruba o vínculo.
 */
class WorkshopRecordsArrivalNotifier
{
    public function __construct(private readonly OwnerlessMaintenanceService $ownerless) {}

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
