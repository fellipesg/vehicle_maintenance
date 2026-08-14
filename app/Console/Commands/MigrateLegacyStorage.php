<?php

namespace App\Console\Commands;

use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class MigrateLegacyStorage extends Command
{
    protected $signature = 'storage:migrate-legacy
        {--prefix= : Only copy files under this folder}
        {--dry-run : List what would be copied without writing}';

    protected $description = 'Copies files from the legacy bucket into the current one';

    public function handle(): int
    {
        $legacyConfig = config('filesystems.disks.legacy_s3');

        if (($legacyConfig['bucket'] ?? '') === '' || ($legacyConfig['key'] ?? '') === '') {
            $this->error('Configure the LEGACY_AWS_* variables before running this command.');

            return self::FAILURE;
        }

        $legacy = Storage::disk('legacy_s3');
        $target = Storage::build(array_merge(
            config('filesystems.disks.'.AppStorage::diskName()),
            ['throw' => true, 'report' => true]
        ));

        $this->line('from: '.$legacyConfig['bucket'].' @ '.$legacyConfig['endpoint']);
        $this->line('to:   '.AppStorage::diskName());
        $this->newLine();

        $prefix = (string) ($this->option('prefix') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $files = $legacy->allFiles($prefix);
        } catch (\Throwable $e) {
            $this->error('Could not list the legacy bucket: '.$e::class.': '.$e->getMessage());

            return self::FAILURE;
        }

        if ($files === []) {
            $this->warn('legacy bucket has no files'.($prefix !== '' ? " under {$prefix}" : ''));

            return self::SUCCESS;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $path) {
            if ($this->alreadyCopied($legacy, $target, $path)) {
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
                $stream = $legacy->readStream($path);

                if ($stream === false || $stream === null) {
                    throw new \RuntimeException('legacy disk returned no stream');
                }

                $target->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $this->info("  copied {$path}");
                $copied++;
            } catch (\Throwable $e) {
                $this->error("  FAILED {$path}: ".$e::class.': '.$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line(($dryRun ? 'would copy' : 'copied').": {$copied}  skipped: {$skipped}  failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function alreadyCopied(Filesystem $legacy, Filesystem $target, string $path): bool
    {
        try {
            return $target->exists($path) && $target->size($path) === $legacy->size($path);
        } catch (\Throwable) {
            return false;
        }
    }
}
