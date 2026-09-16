<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverCropper;
use App\Services\Vehicle\VehicleCoverService;
use App\Support\AppStorage;
use Illuminate\Console\Command;

class GenerateVehicleCoverThumbsCommand extends Command
{
    protected $signature = 'vehicles:generate-cover-thumbs {--missing : Only generate thumbnails that are not stored yet}';

    protected $description = 'Generate 192×192 JPEG thumbnails for vehicle cover photos';

    public function handle(VehicleCoverCropper $cropper, VehicleCoverService $covers): int
    {
        $query = Vehicle::query()
            ->where(function ($builder): void {
                $builder->where(function ($q): void {
                    $q->whereNotNull('cover_photo_path')->where('cover_photo_path', '!=', '');
                })->orWhere(function ($q): void {
                    $q->whereNotNull('cover_photo_portrait_path')->where('cover_photo_portrait_path', '!=', '');
                });
            })
            ->orderBy('id');

        if ($this->option('missing')) {
            $query->where(function ($builder): void {
                $builder->whereNull('cover_photo_thumb_path')
                    ->orWhere('cover_photo_thumb_path', '');
            });
        }

        $vehicles = $query->get();

        if ($vehicles->isEmpty()) {
            $this->info('Nenhum veículo pendente de thumbnail.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($vehicles as $vehicle) {
            $sourcePath = $vehicle->cover_photo_portrait_path ?: $vehicle->cover_photo_path;

            if ($sourcePath === null || $sourcePath === '') {
                continue;
            }

            $copy = AppStorage::localCopy($sourcePath);
            $bytes = $copy['content'] ?? null;

            if ($bytes === null && isset($copy['path']) && is_readable($copy['path'])) {
                $bytes = (string) file_get_contents($copy['path']);
            }

            if ($bytes === null || $bytes === '') {
                $this->warn("Não foi possível ler a capa do veículo {$vehicle->id}.");

                continue;
            }

            $covers->deleteStoredThumb($vehicle);
            $thumbPath = $covers->storeThumbBytes($vehicle, $bytes);
            $vehicle->update(['cover_photo_thumb_path' => $thumbPath]);
            $updated++;
        }

        $this->info("Thumbnails gerados: {$updated}.");

        return self::SUCCESS;
    }
}
