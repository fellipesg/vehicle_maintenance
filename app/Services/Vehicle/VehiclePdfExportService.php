<?php

namespace App\Services\Vehicle;

use App\Jobs\GenerateVehicleMaintenancePdfExport;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Support\AppStorage;
use Illuminate\Support\Facades\Gate;
use Throwable;

class VehiclePdfExportService
{
    public function createExport(User $user, Vehicle $vehicle): VehiclePdfExport
    {
        Gate::authorize('view', $vehicle);

        $export = VehiclePdfExport::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => VehiclePdfExport::STATUS_PENDING,
        ]);

        GenerateVehicleMaintenancePdfExport::dispatch($export->id);

        return $export;
    }

    public function userCanViewExport(User $user, VehiclePdfExport $export): bool
    {
        return $export->user_id === $user->id || $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(VehiclePdfExport $export): array
    {
        $payload = [
            'export_id' => $export->id,
            'status' => $export->status,
        ];

        if ($export->isCompleted()) {
            $downloadUrlExpiresAt = now()->addMinutes(
                (int) config('vehicle-pdf-export.download_url_expiry_minutes')
            );

            $payload['filename'] = $export->filename;
            $payload['download_url'] = $export->file_path !== null
                ? AppStorage::url($export->file_path, $downloadUrlExpiresAt)
                : null;
            $payload['download_url_expires_at'] = $downloadUrlExpiresAt->toIso8601String();
            $payload['expires_at'] = $export->expires_at?->toIso8601String();
            $payload['completed_at'] = $export->completed_at?->toIso8601String();
        } elseif ($export->isFailed()) {
            $payload['error_message'] = $export->error_message;
            $payload['completed_at'] = $export->completed_at?->toIso8601String();
        }

        return $payload;
    }

    public function statusUrl(VehiclePdfExport $export): string
    {
        return '/api/v1/vehicle-pdf-exports/'.$export->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function queuedPayload(VehiclePdfExport $export): array
    {
        return [
            'export_id' => $export->id,
            'status' => $export->status,
            'status_url' => $this->statusUrl($export),
        ];
    }

    public static function sanitizeErrorMessage(?Throwable $exception): string
    {
        if (app()->hasDebugModeEnabled() && $exception !== null) {
            return $exception->getMessage();
        }

        return 'Unable to generate PDF. Please try again later.';
    }
}
