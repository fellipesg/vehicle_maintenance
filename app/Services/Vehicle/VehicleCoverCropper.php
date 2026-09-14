<?php

namespace App\Services\Vehicle;

use RuntimeException;

class VehicleCoverCropper
{
    public function cropToLandscape(string $imageBytes): string
    {
        return $this->centerCrop($imageBytes, 16, 9);
    }

    public function cropToPortrait(string $imageBytes): string
    {
        return $this->centerCrop($imageBytes, 9, 16);
    }

    private function centerCrop(string $imageBytes, int $ratioW, int $ratioH): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD extension is required to crop vehicle cover photos.');
        }

        $source = @imagecreatefromstring($imageBytes);

        if ($source === false) {
            throw new RuntimeException('Unable to decode vehicle cover image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            throw new RuntimeException('Invalid vehicle cover image dimensions.');
        }

        $targetRatio = $ratioW / $ratioH;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($cropHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($cropWidth / $targetRatio);
        }

        $cropX = (int) max(0, floor(($sourceWidth - $cropWidth) / 2));
        $cropY = (int) max(0, floor(($sourceHeight - $cropHeight) / 2));

        $outputWidth = min(1600, $cropWidth);
        $outputHeight = (int) round($outputWidth / $targetRatio);

        $dest = imagecreatetruecolor($outputWidth, $outputHeight);
        imagecopyresampled(
            $dest,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $outputWidth,
            $outputHeight,
            $cropWidth,
            $cropHeight,
        );

        ob_start();
        imagejpeg($dest, null, 85);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($dest);

        return $jpeg;
    }
}
