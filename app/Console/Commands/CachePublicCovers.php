<?php

namespace App\Console\Commands;

use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class CachePublicCovers extends Command
{
    protected $signature = 'storage:cache-public-covers
        {--dry-run : List objects without re-uploading}
        {--force-remote : Exit with failure when R2 is not configured}';

    protected $description = 'Re-upload vehicle covers and workshop logos with public Cache-Control headers';

    public function handle(): int
    {
        $diskName = AppStorage::coversDiskName();
        $config = config('filesystems.disks.'.$diskName);

        if (! is_array($config) || ($config['driver'] ?? '') !== 's3') {
            $this->warn('R2/covers disk is not configured (VEHICLE_COVERS_DISK + R2 credentials).');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        if (($config['key'] ?? '') === '' || ($config['endpoint'] ?? '') === '') {
            $this->warn('R2 credentials missing (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_ENDPOINT).');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        $disk = Storage::build(array_merge($config, ['throw' => true, 'report' => true]));
        $dryRun = (bool) $this->option('dry-run');
        $prefixes = [
            AppStorage::COVERS_PREFIX,
            AppStorage::WORKSHOP_LOGOS_PREFIX,
        ];

        $this->line("Scanning {$diskName} ({$config['bucket']}) for cache-eligible objects");
        $this->line('Cache-Control: '.AppStorage::PUBLIC_CACHE_CONTROL);
        $this->newLine();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($prefixes as $prefix) {
            try {
                $files = $disk->allFiles($prefix);
            } catch (\Throwable $exception) {
                $this->error("Failed to list {$prefix}: ".$exception->getMessage());
                $failed++;

                continue;
            }

            if ($files === []) {
                $this->line("  no files under {$prefix}");

                continue;
            }

            foreach ($files as $path) {
                if (! AppStorage::isPublicCacheEligiblePath($path)) {
                    $this->warn("  skip ineligible {$path}");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("  would re-put {$path}");
                    $updated++;

                    continue;
                }

                try {
                    $this->reputWithCacheHeaders($disk, $path);
                    $this->info("  updated {$path}");
                    $updated++;
                } catch (\Throwable $exception) {
                    $this->error("  FAILED {$path}: ".$exception->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->line(($dryRun ? 'would update' : 'updated').": {$updated}  skipped: {$skipped}  failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function reputWithCacheHeaders(Filesystem $disk, string $path): void
    {
        $contents = $disk->get($path);

        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException('object has no readable content');
        }

        $disk->put($path, $contents, AppStorage::publicObjectOptions());
    }
}
