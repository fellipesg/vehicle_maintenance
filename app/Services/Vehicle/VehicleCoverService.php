<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;

class VehicleCoverService
{
    public function store(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        return $this->storeLandscape($vehicle, $file);
    }

    public function storeLandscape(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $vehicle->id.'_landscape_'.time().'.'.$extension;
        $filePath = AppStorage::COVERS_PREFIX.$fileName;

        $this->deleteStored($vehicle->cover_photo_path);

        AppStorage::putPublic($filePath, (string) file_get_contents($file->getRealPath()));

        $vehicle->update(['cover_photo_path' => $filePath]);

        return $vehicle->fresh();
    }

    public function storePortrait(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $vehicle->id.'_portrait_'.time().'.'.$extension;
        $filePath = AppStorage::COVERS_PREFIX.$fileName;

        $this->deleteStored($vehicle->cover_photo_portrait_path);

        AppStorage::putPublic($filePath, (string) file_get_contents($file->getRealPath()));

        $vehicle->update(['cover_photo_portrait_path' => $filePath]);

        return $vehicle->fresh();
    }

    public function storeLandscapeBytes(Vehicle $vehicle, string $bytes, string $extension = 'jpg'): Vehicle
    {
        $filePath = AppStorage::COVERS_PREFIX.$vehicle->id.'_landscape_'.time().'.'.$extension;

        $this->deleteStored($vehicle->cover_photo_path);
        AppStorage::putPublic($filePath, $bytes);
        $vehicle->update(['cover_photo_path' => $filePath]);

        return $vehicle->fresh();
    }

    public function storePortraitBytes(Vehicle $vehicle, string $bytes, string $extension = 'jpg'): Vehicle
    {
        $filePath = AppStorage::COVERS_PREFIX.$vehicle->id.'_portrait_'.time().'.'.$extension;

        $this->deleteStored($vehicle->cover_photo_portrait_path);
        AppStorage::putPublic($filePath, $bytes);
        $vehicle->update(['cover_photo_portrait_path' => $filePath]);

        return $vehicle->fresh();
    }

    public function deleteStored(?string $coverPhotoPath): void
    {
        if ($coverPhotoPath === null || $coverPhotoPath === '') {
            return;
        }

        if (AppStorage::coversDisk()->exists($coverPhotoPath)) {
            AppStorage::coversDisk()->delete($coverPhotoPath);
        }
    }
}
