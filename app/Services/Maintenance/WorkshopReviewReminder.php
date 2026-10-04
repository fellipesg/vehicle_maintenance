<?php

namespace App\Services\Maintenance;

use App\Enums\WorkshopReviewStatus;
use App\Models\Maintenance;
use App\Models\Workshop;
use App\Notifications\WorkshopReviewReminderNotification;
use App\Services\FcmService;

/**
 * Lembra a oficina dos pedidos de validação parados. Cada pedido pendente há AFTER_DAYS dias ou
 * mais gera no máximo um lembrete (workshop_review_reminded_at); a oficina recebe um aviso só,
 * com o total, por e-mail, sino e push.
 */
class WorkshopReviewReminder
{
    public const AFTER_DAYS = 7;

    /**
     * @return int Oficinas lembradas.
     */
    public function dispatchDueReminders(): int
    {
        $staleByWorkshop = Maintenance::query()
            ->where('workshop_review_status', WorkshopReviewStatus::Pending->value)
            ->whereNull('verified_at')
            ->whereNotNull('workshop_id')
            ->whereNull('workshop_review_reminded_at')
            ->where('workshop_review_requested_at', '<=', now()->subDays(self::AFTER_DAYS))
            ->get(['id', 'workshop_id'])
            ->groupBy('workshop_id');

        $reminded = 0;

        foreach ($staleByWorkshop as $workshopId => $staleMaintenances) {
            $workshopUser = Workshop::query()->with('user')->find($workshopId)?->user;

            if ($workshopUser === null) {
                continue;
            }

            $pendingCount = Maintenance::query()->awaitingReviewBy((int) $workshopId)->count();
            $notification = new WorkshopReviewReminderNotification($staleMaintenances->count(), $pendingCount);

            $workshopUser->notify($notification);

            try {
                app(FcmService::class)->sendToUser($workshopUser->id, $notification->title(), $notification->body(), [
                    'type' => 'workshop-review-reminder',
                    'pending_count' => (string) $pendingCount,
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }

            Maintenance::query()
                ->whereKey($staleMaintenances->pluck('id'))
                ->update(['workshop_review_reminded_at' => now()]);

            $reminded++;
        }

        return $reminded;
    }
}
