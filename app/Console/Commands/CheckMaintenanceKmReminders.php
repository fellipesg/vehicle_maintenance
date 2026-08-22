<?php

namespace App\Console\Commands;

use App\Services\Vehicle\VehicleMaintenanceReminderDispatcher;
use Illuminate\Console\Command;

class CheckMaintenanceKmReminders extends Command
{
    protected $signature = 'maintenance:check-km-reminders';

    protected $description = 'Envia lembretes de revisão por quilometragem (e-mail + notificação in-app)';

    public function handle(VehicleMaintenanceReminderDispatcher $dispatcher): int
    {
        $sent = $dispatcher->dispatchDueReminders();

        $this->info("Lembretes enviados: {$sent}");

        return self::SUCCESS;
    }
}
