<?php

namespace App\Console\Commands;

use App\Models\VehiclePdfExport;
use App\Support\AppStorage;
use Illuminate\Console\Command;

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
