<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceAttachmentPurger;
use Illuminate\Console\Command;

/**
 * Retenção (LGPD): notas fiscais e fotos de uma OS sem proprietário que ele não aceitou em
 * maintenance.pending_attachments_retention_days (90) são apagadas. A OS e os itens ficam, na forma
 * mínima, e o status dos anexos vira declined. Anexos aceitos nunca são tocados.
 */
class PurgePendingMaintenanceAttachments extends Command
{
    protected $signature = 'maintenance:purge-pending-attachments {--days= : Substitui maintenance.pending_attachments_retention_days}';

    protected $description = 'Apaga notas e fotos pendentes de OS sem proprietário depois do prazo de retenção';

    public function handle(MaintenanceAttachmentPurger $purger): int
    {
        $days = (int) ($this->option('days') ?: config('maintenance.pending_attachments_retention_days', 90));
        $cutoff = now()->subDays($days);
        $purgedRecords = 0;

        Maintenance::query()
            ->where('attachments_status', Maintenance::ATTACHMENTS_PENDING)
            ->whereNotNull('owner_status')
            ->orderBy('id')
            ->each(function (Maintenance $maintenance) use ($purger, $cutoff, &$purgedRecords): void {
                $newest = collect([
                    $maintenance->invoices()->max('created_at'),
                    $maintenance->photos()->max('created_at'),
                ])->filter()->map(fn ($value) => \Illuminate\Support\Carbon::parse($value))->max();

                if ($newest !== null && $newest->greaterThan($cutoff)) {
                    return;
                }

                $purger->purge($maintenance);
                $maintenance->forceFill(['attachments_status' => Maintenance::ATTACHMENTS_DECLINED])->saveQuietly();
                $purgedRecords++;
            });

        $this->info("Anexos pendentes apagados em {$purgedRecords} OS.");

        return self::SUCCESS;
    }
}
