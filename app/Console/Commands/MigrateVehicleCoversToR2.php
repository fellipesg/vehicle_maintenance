<?php

namespace App\Console\Commands;

use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class MigrateVehicleCoversToR2 extends Command
{
    protected $signature = 'storage:migrate-covers-to-r2
        {--from= : Source disk (default: current covers disk before switching VEHICLE_COVERS_DISK)}
        {--dry-run : List what would be copied without writing}';

    protected $description = 'Copia vehicle-covers/* para o bucket Cloudflare R2';

    public function handle(): int
    {
        $targetDiskName = 'r2';
        $targetConfig = config('filesystems.disks.'.$targetDiskName);

        if (($targetConfig['key'] ?? '') === '' || ($targetConfig['endpoint'] ?? '') === '') {
            $this->error('Configure R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY e R2_ENDPOINT antes de migrar.');

            return self::FAILURE;
        }

        $sourceDiskName = (string) ($this->option('from') ?: AppStorage::diskName());
        $source = Storage::disk($sourceDiskName);
        $target = Storage::build(array_merge($targetConfig, ['throw' => true, 'report' => true]));

        $this->line("from: {$sourceDiskName}");
        $this->line("to:   {$targetDiskName} ({$targetConfig['bucket']})");
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');

        try {
            $files = $source->allFiles(AppStorage::COVERS_PREFIX);
        } catch (\Throwable $exception) {
            $this->error('Não foi possível listar as capas na origem: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($files === []) {
            $this->warn('Nenhum arquivo em '.AppStorage::COVERS_PREFIX);

            return self::SUCCESS;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $path) {
            if ($this->alreadyCopied($source, $target, $path)) {
                $this->line("  skip {$path}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  would copy {$path}");
                $copied++;

                continue;
            }

            try {
                $stream = $source->readStream($path);

                if (! is_resource($stream)) {
                    throw new \RuntimeException('origem não retornou stream');
                }

                $target->writeStream($path, $stream);
                fclose($stream);

                $this->info("  copied {$path}");
                $copied++;
            } catch (\Throwable $exception) {
                $this->error("  FAILED {$path}: ".$exception->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line(($dryRun ? 'would copy' : 'copied').": {$copied}  skipped: {$skipped}  failed: {$failed}");

        if (! $dryRun && $failed === 0) {
            $this->newLine();
            $this->info('Defina VEHICLE_COVERS_DISK=r2 e R2_PUBLIC_URL no .env (e no Laravel Cloud).');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function alreadyCopied(Filesystem $source, Filesystem $target, string $path): bool
    {
        try {
            return $target->exists($path) && $target->size($path) === $source->size($path);
        } catch (\Throwable) {
            return false;
        }
    }
}
