<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetVehicleCover extends Command
{
    protected $signature = 'vehicles:set-cover {plate : Vehicle license plate} {path : Local file path to upload as cover}';

    protected $description = 'Upload a cover photo for a vehicle by license plate';

    public function handle(): int
    {
        $plate = strtoupper(trim($this->argument('plate')));
        $path = $this->argument('path');

        if (! File::isFile($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $vehicle = Vehicle::query()
            ->where('license_plate', $plate)
            ->first();

        if ($vehicle === null) {
            $this->error("Vehicle not found for plate: {$plate}");

            return self::FAILURE;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = $vehicle->id.'_'.time().'.'.$extension;
        $storagePath = 'vehicle-covers/'.$fileName;

        if ($vehicle->cover_photo_path && AppStorage::disk()->exists($vehicle->cover_photo_path)) {
            AppStorage::disk()->delete($vehicle->cover_photo_path);
        }

        AppStorage::disk()->put($storagePath, File::get($path));

        $vehicle->update(['cover_photo_path' => $storagePath]);

        $this->info("Cover photo set for {$vehicle->brand} {$vehicle->model} ({$vehicle->license_plate})");
        $this->line('Storage path: '.$storagePath);
        $this->line('URL: '.AppStorage::url($storagePath));

        return self::SUCCESS;
    }
}
