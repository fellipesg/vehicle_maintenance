<?php

namespace App\Services\Workshop;

use App\Models\Workshop;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;

class WorkshopLogoService
{
    public function store(Workshop $workshop, UploadedFile $file): Workshop
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $workshop->id.'_'.time().'.'.$extension;
        $filePath = AppStorage::WORKSHOP_LOGOS_PREFIX.$fileName;

        $this->deleteStored($workshop->logo_path);

        AppStorage::putPublic($filePath, (string) file_get_contents($file->getRealPath()));

        $workshop->update(['logo_path' => $filePath]);

        return $workshop->fresh();
    }

    public function deleteStored(?string $logoPath): void
    {
        if ($logoPath === null || $logoPath === '') {
            return;
        }

        if (AppStorage::coversDisk()->exists($logoPath)) {
            AppStorage::coversDisk()->delete($logoPath);
        }
    }
}
