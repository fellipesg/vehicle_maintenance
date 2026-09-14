<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Support\AppStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MaintenancePhotoService
{
    public function store(
        Maintenance $maintenance,
        UploadedFile $file,
        string $subject,
        string $stage,
        User $user,
        ?int $maintenanceItemId = null,
    ): MaintenancePhoto {
        $this->assertGroupLimit($maintenance, $subject, $stage);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName = $maintenance->id.'_'.uniqid('', true).'.'.$extension;
        $path = AppStorage::MAINTENANCE_PHOTOS_PREFIX.$fileName;

        $contents = $this->processedContents($file);
        AppStorage::disk()->put($path, $contents);

        $sort = (int) MaintenancePhoto::query()
            ->where('maintenance_id', $maintenance->id)
            ->where('subject', $subject)
            ->where('stage', $stage)
            ->max('sort') + 1;

        return MaintenancePhoto::create([
            'maintenance_id' => $maintenance->id,
            'maintenance_item_id' => $maintenanceItemId,
            'subject' => $subject,
            'stage' => $stage,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'sort' => $sort,
            'created_by' => $user->id,
        ]);
    }

    public function delete(MaintenancePhoto $photo): void
    {
        if (AppStorage::disk()->exists($photo->path)) {
            AppStorage::disk()->delete($photo->path);
        }

        $photo->delete();
    }

    public function assertGroupLimit(Maintenance $maintenance, string $subject, string $stage): void
    {
        $count = MaintenancePhoto::query()
            ->where('maintenance_id', $maintenance->id)
            ->where('subject', $subject)
            ->where('stage', $stage)
            ->count();

        if ($count >= MaintenancePhoto::MAX_PER_GROUP) {
            throw ValidationException::withMessages([
                'photo' => 'Limite de '.MaintenancePhoto::MAX_PER_GROUP.' fotos atingido para este grupo.',
            ]);
        }
    }

    private function processedContents(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                'photo' => 'Não foi possível ler o arquivo enviado.',
            ]);
        }

        if (! function_exists('imagecreatefromstring')) {
            return $contents;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return $contents;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = 1920;

        if ($width <= $maxWidth) {
            imagedestroy($image);

            return $contents;
        }

        $newWidth = $maxWidth;
        $newHeight = (int) round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        $mime = $file->getMimeType();
        ob_start();
        match ($mime) {
            'image/png' => imagepng($resized, null, 8),
            'image/webp' => function_exists('imagewebp') ? imagewebp($resized, null, 85) : imagejpeg($resized, null, 85),
            default => imagejpeg($resized, null, 85),
        };
        imagedestroy($resized);
        $output = ob_get_clean();

        return is_string($output) && $output !== '' ? $output : $contents;
    }
}
