<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandAppIconCropTest extends TestCase
{
    public function test_app_icon_is_the_full_odometer_mark_not_the_d_label(): void
    {
        $path = dirname(__DIR__, 2).'/public/images/brand/app-icon.png';
        $image = $this->loadPng($path);

        $this->assertSame(imagesx($image), imagesy($image), 'App icon must be square.');
        $this->assertGreaterThanOrEqual(180, imagesx($image));
        $this->assertLogoIsFullOdometer($image);
        imagedestroy($image);
    }

    public function test_android_adaptive_foreground_drops_the_teal_card_frame(): void
    {
        $path = dirname(__DIR__, 3).'/frontend/android/app/src/main/res/drawable/ic_launcher_foreground.png';
        $image = $this->loadPng($path);

        $cornerTeal = $this->countMatchingPixels(
            $image,
            0,
            0,
            (int) floor(imagesx($image) * 0.12),
            (int) floor(imagesy($image) * 0.12),
            $this->isTeal(...),
        );
        $this->assertLessThan(40, $cornerTeal, 'Adaptive foreground still has the outer teal frame in the mask corners.');

        $this->assertMarkIsHorizontallyCentered($image);

        imagedestroy($image);
    }

    public function test_ios_marketing_icon_keeps_the_odometer_inside_the_safe_zone(): void
    {
        $path = dirname(__DIR__, 3).'/frontend/ios/Runner/Assets.xcassets/AppIcon.appiconset/Icon-App-1024x1024@1x.png';
        $image = $this->loadPng($path);

        $this->assertSame(1024, imagesx($image));
        $this->assertSame(1024, imagesy($image));
        $this->assertLogoIsFullOdometer($image);
        imagedestroy($image);
    }

    private function loadPng(string $path): \GdImage
    {
        $this->assertFileExists($path);
        $image = imagecreatefrompng($path);
        $this->assertNotFalse($image);

        return $image;
    }

    /**
     * The old crop (772, 108) captured the "D" caption and the top of the mark.
     * A correct crop is navy at the edges, teal in the middle, no leftover letters.
     */
    private function assertLogoIsFullOdometer(\GdImage $image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $topBandBright = $this->countMatchingPixels(
            $image,
            0,
            0,
            $width - 1,
            (int) floor($height * 0.10),
            $this->isBrightLetter(...),
        );
        $this->assertSame(0, $topBandBright, 'Top of the icon still has the "D" caption.');

        $leftBandBright = $this->countMatchingPixels(
            $image,
            0,
            0,
            (int) floor($width * 0.06),
            $height - 1,
            $this->isBrightLetter(...),
        );
        $this->assertLessThan(8, $leftBandBright, 'Left edge still has leftover lockup letters.');

        $centerTeal = $this->countMatchingPixels(
            $image,
            (int) floor($width * 0.30),
            (int) floor($height * 0.30),
            (int) floor($width * 0.70),
            (int) floor($height * 0.70),
            $this->isTeal(...),
        );
        $this->assertGreaterThan(80, $centerTeal, 'Center of the icon is missing the odometer.');
    }

    /**
     * @param  callable(int, int, int): bool  $predicate
     */
    private function countMatchingPixels(
        \GdImage $image,
        int $x0,
        int $y0,
        int $x1,
        int $y1,
        callable $predicate,
    ): int {
        $count = 0;

        for ($y = $y0; $y <= $y1; $y++) {
            for ($x = $x0; $x <= $x1; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($predicate($r, $g, $b)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function isBrightLetter(int $r, int $g, int $b): bool
    {
        return $r > 200 && $g > 200 && $b > 200;
    }

    private function isTeal(int $r, int $g, int $b): bool
    {
        return $g > 120 && $b > 90 && $g > $r + 20;
    }

    private function assertMarkIsHorizontallyCentered(\GdImage $image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $sumX = 0;
        $count = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($this->isBrightLetter($r, $g, $b) || $this->isTeal($r, $g, $b)) {
                    $sumX += $x;
                    $count++;
                }
            }
        }

        $this->assertGreaterThan(0, $count);
        $centroidX = $sumX / $count;
        $this->assertEqualsWithDelta($width / 2, $centroidX, 3.0, 'Launcher mark is off-center horizontally.');
    }
}
