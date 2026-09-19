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

    protected $description = 'Upload public/images/brand/* and public/images/landing/* to the public R2 CDN bucket';

    public function handle(): int
    {
        $diskName = AppStorage::coversDiskName();
        $config = config('filesystems.disks.'.$diskName);

        if (! is_array($config) || ($config['driver'] ?? '') !== 's3') {
            $this->warn('R2/covers disk is not configured (VEHICLE_COVERS_DISK + R2 credentials).');
            $this->line('Local files remain under public/images/.');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        if (($config['key'] ?? '') === '' || ($config['endpoint'] ?? '') === '') {
            $this->warn('R2 credentials missing (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_ENDPOINT).');
            $this->line('Local files remain under public/images/.');

            return $this->option('force-remote') ? self::FAILURE : self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::build(array_merge($config, ['throw' => true, 'report' => true]));

        $this->line("Uploading to {$diskName} ({$config['bucket']})");
        $this->newLine();

        $uploaded = 0;
        $failed = 0;

        foreach ($this->assetSets() as $set) {
            if (! is_dir($set['dir'])) {
                $this->warn("Directory not found: {$set['dir']}");

                continue;
            }

            $files = glob($set['dir'].'/*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];

            if ($files === []) {
                $this->warn("No files found in {$set['dir']}.");

                continue;
            }

            $this->line($set['label'].' → '.$set['prefix']);

            foreach ($files as $localPath) {
                $filename = basename($localPath);
                $remotePath = $set['prefix'].$filename;

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
        }

        $this->line(($dryRun ? 'would upload' : 'uploaded').": {$uploaded}  failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{label: string, dir: string, prefix: string}>
     */
    private function assetSets(): array
    {
        return [
            [
                'label' => 'Brand',
                'dir' => public_path('images/brand'),
                'prefix' => AppStorage::BRAND_PREFIX,
            ],
            [
                'label' => 'Landing',
                'dir' => public_path('images/landing'),
                'prefix' => AppStorage::LANDING_PREFIX,
            ],
        ];
    }
}
