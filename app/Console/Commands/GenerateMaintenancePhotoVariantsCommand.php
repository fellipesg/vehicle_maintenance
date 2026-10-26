<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMaintenancePhoto;
use App\Models\MaintenancePhoto;
use App\Services\Maintenance\MaintenancePhotoVariantGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill das fotos de manutenção sem variantes: enfileira o ProcessMaintenancePhoto de cada foto
 * com processed_at nulo. Foto antiga também ganha um master novo, sem EXIF/GPS, no layout novo; o
 * arquivo antigo NUNCA é apagado (fica em legacy_path). Pode rodar de novo: o que já foi processado
 * fica de fora. --dry-run só conta; --limit=N corta a execução.
 */
class GenerateMaintenancePhotoVariantsCommand extends Command
{
    protected $signature = 'photos:generate-variants
        {--sync : Processa na hora, sem fila}
        {--dry-run : Só informa quantas fotos seriam processadas}
        {--limit= : Processa no máximo N fotos}
        {--chunk=100 : Fotos lidas por vez}';

    protected $description = 'Gera thumb e display (e master sem EXIF) das fotos de manutenção ainda não processadas';

    public function handle(MaintenancePhotoVariantGenerator $variants): int
    {
        $sync = (bool) $this->option('sync');
        $limit = $this->option('limit') !== null && $this->option('limit') !== '' ? max(0, (int) $this->option('limit')) : null;
        $chunk = max(1, (int) $this->option('chunk'));

        $query = MaintenancePhoto::query()->whereNull('processed_at');

        if ($this->option('dry-run')) {
            $pending = $query->count();
            $this->info('Fotos a processar: '.($limit === null ? $pending : min($pending, $limit)).' de '.$pending.' pendentes (dry-run, nada foi alterado).');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;
        $handled = 0;

        $query->chunkById($chunk, function ($photos) use ($variants, $sync, $limit, &$processed, &$failed, &$handled): bool {
            foreach ($photos as $photo) {
                if ($limit !== null && $handled >= $limit) {
                    return false;
                }

                $handled++;

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

            return true;
        });

        $this->info(($sync ? "Fotos processadas: {$processed}." : "Fotos enfileiradas: {$processed}.").($failed > 0 ? " Falhas: {$failed}." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
