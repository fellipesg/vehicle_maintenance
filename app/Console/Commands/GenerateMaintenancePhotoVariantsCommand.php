<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMaintenancePhoto;
use App\Models\MaintenancePhoto;
use App\Services\Maintenance\MaintenancePhotoVariantGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill das fotos de manutenção sem variantes: enfileira o ProcessMaintenancePhoto de cada foto
 * com processed_at nulo. Foto antiga também ganha um master novo, sem EXIF/GPS, no layout novo.
 * Pode rodar de novo: o que já foi processado fica de fora.
 */
class GenerateMaintenancePhotoVariantsCommand extends Command
{
    protected $signature = 'photos:generate-variants
        {--sync : Processa na hora, sem fila}
        {--chunk=100 : Fotos lidas por vez}';

    protected $description = 'Gera thumb e display (e master sem EXIF) das fotos de manutenção ainda não processadas';

    public function handle(MaintenancePhotoVariantGenerator $variants): int
    {
        $sync = (bool) $this->option('sync');
        $chunk = max(1, (int) $this->option('chunk'));
        $processed = 0;
        $failed = 0;

        MaintenancePhoto::query()
            ->whereNull('processed_at')
            ->chunkById($chunk, function ($photos) use ($variants, $sync, &$processed, &$failed): void {
                foreach ($photos as $photo) {
                    if (! $sync) {
                        ProcessMaintenancePhoto::dispatch($photo);
                        $processed++;

                        continue;
                    }

                    try {
                        $variants->generate($photo);
                        $processed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info(($sync ? "Fotos processadas: {$processed}." : "Fotos enfileiradas: {$processed}.").($failed > 0 ? " Falhas: {$failed}." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
