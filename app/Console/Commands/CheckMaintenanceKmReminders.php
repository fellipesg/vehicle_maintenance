<?php

namespace App\Console\Commands;

use App\Services\Vehicle\VehicleMaintenanceReminderDispatcher;
use App\Services\Workshop\WorkshopMessageTemplateDispatcher;
use Illuminate\Console\Command;

class CheckMaintenanceKmReminders extends Command
{
    protected $signature = 'maintenance:check-km-reminders';

    protected $description = 'Envia lembretes de revisão por quilometragem (e-mail + notificação in-app)';

    public function handle(
        VehicleMaintenanceReminderDispatcher $ownerDispatcher,
        WorkshopMessageTemplateDispatcher $workshopDispatcher,
    ): int {
        $ownerSent = $ownerDispatcher->dispatchDueReminders();
        $workshopSent = $workshopDispatcher->dispatchFollowUps();

        $this->info("Lembretes enviados: {$ownerSent}");
        $this->info("Follow-ups de oficina enviados: {$workshopSent}");

        return self::SUCCESS;
    }
}
