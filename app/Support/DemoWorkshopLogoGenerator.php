<?php

namespace App\Support;

class DemoWorkshopLogoGenerator
{
    public const WIDTH = 120;

    public const HEIGHT = 130;

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function jpeg(
        string $label,
        array $rgb,
        int $width = self::WIDTH,
        int $height = self::HEIGHT,
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension is required to generate demo workshop logos.');
        }

        [$red, $green, $blue] = $rgb;

        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, $red, $green, $blue);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);

        $accent = imagecolorallocate(
            $image,
            min(255, $red + 40),
            min(255, $green + 40),
            min(255, $blue + 40),
        );
        imagefilledrectangle($image, 12, 12, $width - 13, $height - 13, $accent);

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textX = max(16, (int) (($width - $textWidth) / 2));
        $textY = (int) (($height - imagefontheight($font)) / 2);
        imagestring($image, $font, $textX, $textY, $label, $textColor);

        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        $jpeg = ob_get_clean();

        if (! is_string($jpeg) || $jpeg === '') {
            throw new \RuntimeException('Failed to generate demo workshop logo JPEG.');
        }

        return $jpeg;
    }
}
