<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;

class VehicleCoverService
{
    public function store(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $vehicle->id.'_'.time().'.'.$extension;
        $filePath = $file->storeAs('vehicle-covers', $fileName, AppStorage::diskName());

        $this->deleteStored($vehicle->cover_photo_path);

        $vehicle->update(['cover_photo_path' => $filePath]);

        return $vehicle->fresh();
    }

    public function deleteStored(?string $coverPhotoPath): void
    {
        if ($coverPhotoPath === null || $coverPhotoPath === '') {
            return;
        }

        if (AppStorage::disk()->exists($coverPhotoPath)) {
            AppStorage::disk()->delete($coverPhotoPath);
        }
    }
}
