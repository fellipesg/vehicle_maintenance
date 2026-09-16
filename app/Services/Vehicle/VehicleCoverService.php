<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;

class VehicleCoverService
{
    public function __construct(private readonly VehicleCoverCropper $cropper) {}

    public function store(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        return $this->storeLandscape($vehicle, $file);
    }

    public function storeLandscape(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $vehicle->id.'_landscape_'.time().'.'.$extension;
        $filePath = AppStorage::COVERS_PREFIX.$fileName;
        $bytes = (string) file_get_contents($file->getRealPath());

        $this->deleteStored($vehicle->cover_photo_path);
        $this->deleteStoredThumb($vehicle);

        AppStorage::putPublic($filePath, $bytes);
        $thumbPath = $this->storeThumbBytes($vehicle, $bytes);

        $vehicle->update([
            'cover_photo_path' => $filePath,
            'cover_photo_thumb_path' => $thumbPath,
        ]);

        return $vehicle->fresh();
    }

    public function storePortrait(Vehicle $vehicle, UploadedFile $file): Vehicle
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $vehicle->id.'_portrait_'.time().'.'.$extension;
        $filePath = AppStorage::COVERS_PREFIX.$fileName;
        $bytes = (string) file_get_contents($file->getRealPath());

        $this->deleteStored($vehicle->cover_photo_portrait_path);

        AppStorage::putPublic($filePath, $bytes);

        $updates = ['cover_photo_portrait_path' => $filePath];

        if (! $vehicle->hasThumbCover()) {
            $this->deleteStoredThumb($vehicle);
            $updates['cover_photo_thumb_path'] = $this->storeThumbBytes($vehicle, $bytes);
        }

        $vehicle->update($updates);

        return $vehicle->fresh();
    }

    public function storeLandscapeBytes(Vehicle $vehicle, string $bytes, string $extension = 'jpg'): Vehicle
    {
        $filePath = AppStorage::COVERS_PREFIX.$vehicle->id.'_landscape_'.time().'.'.$extension;

        $this->deleteStored($vehicle->cover_photo_path);
        $this->deleteStoredThumb($vehicle);

        AppStorage::putPublic($filePath, $bytes);

        $vehicle->update([
            'cover_photo_path' => $filePath,
            'cover_photo_thumb_path' => $this->storeThumbBytes($vehicle, $bytes),
        ]);

        return $vehicle->fresh();
    }

    public function storePortraitBytes(Vehicle $vehicle, string $bytes, string $extension = 'jpg'): Vehicle
    {
        $filePath = AppStorage::COVERS_PREFIX.$vehicle->id.'_portrait_'.time().'.'.$extension;

        $this->deleteStored($vehicle->cover_photo_portrait_path);
        AppStorage::putPublic($filePath, $bytes);

        $updates = ['cover_photo_portrait_path' => $filePath];

        if (! $vehicle->hasThumbCover()) {
            $this->deleteStoredThumb($vehicle);
            $updates['cover_photo_thumb_path'] = $this->storeThumbBytes($vehicle, $bytes);
        }

        $vehicle->update($updates);

        return $vehicle->fresh();
    }

    public function storeThumbBytes(Vehicle $vehicle, string $sourceBytes): string
    {
        $thumbBytes = $this->cropper->cropToThumb($sourceBytes);
        $filePath = AppStorage::COVERS_PREFIX.$vehicle->id.'_thumb_'.time().'.jpg';

        AppStorage::putPublic($filePath, $thumbBytes);

        return $filePath;
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

    public function deleteStoredThumb(Vehicle $vehicle): void
    {
        $thumbPath = $vehicle->cover_photo_thumb_path;

        if ($thumbPath === null || $thumbPath === '') {
            return;
        }

        if (AppStorage::coversDisk()->exists($thumbPath)) {
            AppStorage::coversDisk()->delete($thumbPath);
        }
    }
}
