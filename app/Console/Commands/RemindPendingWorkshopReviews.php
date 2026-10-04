<?php

namespace App\Console\Commands;

use App\Services\Maintenance\WorkshopReviewReminder;
use Illuminate\Console\Command;

class RemindPendingWorkshopReviews extends Command
{
    protected $signature = 'workshop-reviews:remind';

    protected $description = 'Lembra as oficinas dos serviços declarados que esperam validação há uma semana (e-mail + sino + push)';

    public function handle(WorkshopReviewReminder $reminder): int
    {
        $reminded = $reminder->dispatchDueReminders();

        $this->info("Oficinas lembradas: {$reminded}");

        return self::SUCCESS;
    }
}
