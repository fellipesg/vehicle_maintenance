<?php

namespace App\Console\Commands;

use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PublishBrandToR2 extends Command
{
    protected $signature = 'brand:publish-to-r2
        {--dry-run : List what would be uploaded without writing}
        {--force-remote : Exit with failure when R2 is not configured}';

    protected $description = 'Upload backend/public/images/brand/* to the public R2 covers bucket';

    public function handle(): int
    {
        $sourceDir = public_path('images/brand');

        if (! is_dir($sourceDir)) {
            $this->error("Brand directory not found: {$sourceDir}");

            return self::FAILURE;
        }

        $diskName = AppStorage::coversDiskName();
        $config = config('filesystems.disks.'.$diskName);

        if (! is_array($config) || ($config['driver'] ?? '') !== 's3') {
            $this->warn('R2/covers disk is not configured (VEHICLE_COVERS_DISK + R2 credentials).');
            $this->line('Local brand files remain at public/images/brand/.');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        if (($config['key'] ?? '') === '' || ($config['endpoint'] ?? '') === '') {
            $this->warn('R2 credentials missing (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_ENDPOINT).');
            $this->line('Local brand files remain at public/images/brand/.');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::build(array_merge($config, ['throw' => true, 'report' => true]));

        $files = glob($sourceDir.'/*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];

        if ($files === []) {
            $this->warn('No brand files found in public/images/brand/.');

            return self::SUCCESS;
        }

        $this->line("Uploading to {$diskName} ({$config['bucket']}) under ".AppStorage::BRAND_PREFIX);
        $this->newLine();

        $uploaded = 0;
        $failed = 0;

        foreach ($files as $localPath) {
            $filename = basename($localPath);
            $remotePath = AppStorage::BRAND_PREFIX.$filename;

            if ($dryRun) {
                $this->line("  would put {$remotePath}");
                $uploaded++;

                continue;
            }

            try {
                $disk->put($remotePath, file_get_contents($localPath), AppStorage::publicObjectOptions());

                $publicUrl = AppStorage::coversUrl($remotePath);
                $this->info("  {$filename} → {$publicUrl}");
                $uploaded++;
            } catch (\Throwable $exception) {
                $this->error("  FAILED {$filename}: ".$exception->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line(($dryRun ? 'would upload' : 'uploaded').": {$uploaded}  failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
