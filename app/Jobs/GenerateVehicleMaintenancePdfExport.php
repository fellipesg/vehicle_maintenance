<?php

namespace App\Jobs;

use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Services\Vehicle\VehiclePdfExportService;
use App\Support\AppStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateVehicleMaintenancePdfExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public string $exportId,
    ) {}

    public function handle(VehicleMaintenancePdfExporter $exporter): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($this->timeout);
        }

        $export = VehiclePdfExport::query()->findOrFail($this->exportId);
        $export->update(['status' => VehiclePdfExport::STATUS_PROCESSING]);

        $vehicle = Vehicle::query()->findOrFail($export->vehicle_id);
        $file = null;

        try {
            $file = $exporter->generate($vehicle);
            $path = config('vehicle-pdf-export.storage_path_prefix').'/'.$export->id.'.pdf';

            AppStorage::disk()->put($path, $file['content']);

            $export->update([
                'status' => VehiclePdfExport::STATUS_COMPLETED,
                'file_path' => $path,
                'filename' => $file['filename'],
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('vehicle-pdf-export.file_ttl_hours')),
            ]);
        } finally {
            if (is_array($file)) {
                $exporter->cleanupTemps($file['temps']);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = VehiclePdfExport::query()->find($this->exportId);

        if ($export === null || $export->isCompleted()) {
            return;
        }

        $export->update([
            'status' => VehiclePdfExport::STATUS_FAILED,
            'error_message' => VehiclePdfExportService::sanitizeErrorMessage($exception),
            'completed_at' => now(),
        ]);
    }
}
