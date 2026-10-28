<?php

namespace App\Console\Commands;

use App\Models\VehiclePdfExport;
use App\Support\AppStorage;
use Illuminate\Console\Command;

/**
 * Faxina do arquivo expirado, não o portão: o prazo é cobrado na hora do download
 * (VehiclePdfExport::isExpired, 410 na API e na web), então um arquivo que sobreviveu até a próxima
 * execução já não baixa.
 *
 * Roda de hora em hora, e não uma vez por dia, para encurtar a única brecha que sobra: a URL
 * assinada do storage que o status entregou pouco antes do prazo (download_url_expiry_minutes, 60)
 * continua resolvendo enquanto o arquivo existir. Apagar o arquivo mata essa URL.
 */
class CleanupVehiclePdfExports extends Command
{
    protected $signature = 'vehicle-pdf-exports:cleanup';

    protected $description = 'Delete expired vehicle PDF exports from storage and database';

    public function handle(): int
    {
        $deleted = 0;

        VehiclePdfExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->chunkById(100, function ($exports) use (&$deleted): void {
                foreach ($exports as $export) {
                    if (is_string($export->file_path) && $export->file_path !== '') {
                        AppStorage::disk()->delete($export->file_path);
                    }

                    $export->delete();
                    $deleted++;
                }
            });

        $this->info("Expired vehicle PDF exports removed: {$deleted}");

        return self::SUCCESS;
    }
}
