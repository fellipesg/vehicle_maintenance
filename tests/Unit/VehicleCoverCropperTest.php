<?php

namespace Tests\Unit;

use App\Services\Vehicle\VehicleCoverCropper;
use Tests\TestCase;

class VehicleCoverCropperTest extends TestCase
{
    public function test_crops_to_landscape_16_by_9(): void
    {
        $source = $this->sampleJpeg(1200, 800);

        $cropped = app(VehicleCoverCropper::class)->cropToLandscape($source);
        $image = imagecreatefromstring($cropped);

        $this->assertNotFalse($image);
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        $this->assertEqualsWithDelta(16 / 9, $width / $height, 0.02);
    }

    public function test_crops_to_portrait_9_by_16(): void
    {
        $source = $this->sampleJpeg(1200, 800);

        $cropped = app(VehicleCoverCropper::class)->cropToPortrait($source);
        $image = imagecreatefromstring($cropped);

        $this->assertNotFalse($image);
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        $this->assertEqualsWithDelta(9 / 16, $width / $height, 0.02);
    }

    private function sampleJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 120, 80, 40));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
