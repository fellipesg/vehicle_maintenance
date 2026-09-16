<?php

namespace App\Console\Commands;

use App\Services\Maintenance\MaintenanceVerificationStamper;
use Illuminate\Console\Command;

class VerifyLinkedWorkshopMaintenances extends Command
{
    protected $signature = 'maintenances:verify-linked-workshops
                            {--dry-run : List how many rows would be updated without writing}
                            {--force : Allow running in production for a one-time backfill}';

    protected $description = 'Apply the workshop seal to maintenances that have workshop_id but are not verified yet';

    public function handle(MaintenanceVerificationStamper $stamper): int
    {
        if (! $this->canRun()) {
            $this->error('Refusing to run in production without --force, MAINTENANCE_AUTO_VERIFY_LINKED_WORKSHOP=true, or local/testing env.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $pending = \App\Models\Maintenance::query()
                ->whereNotNull('workshop_id')
                ->whereNull('verified_at')
                ->count();
            $this->info("Would verify {$pending} maintenance(s).");

            return self::SUCCESS;
        }

        $count = $stamper->verifyLinkedWorkshopMaintenances();
        $this->info("Verified {$count} maintenance(s) with linked workshop.");

        return self::SUCCESS;
    }

    private function canRun(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (app()->environment('local', 'testing')) {
            return true;
        }

        return (bool) config('maintenance.auto_verify_linked_workshop');
    }
}
