<?php

namespace Tests\Feature\Concerns;

/**
 * Imagens de teste das fotos de manutenção: um bloco vermelho no canto superior esquerdo (para
 * conferir a orientação aplicada) e, no JPEG, um EXIF com Orientation e GPS embutido à mão.
 */
trait BuildsPhotoFixtures
{
    /**
     * @return string bytes de um JPEG width x height, fundo branco e bloco vermelho 25% no canto superior esquerdo
     */
    protected function plainJpeg(int $width = 300, int $height = 200): string
    {
        $image = $this->markedImage($width, $height);
        ob_start();
        imagejpeg($image, null, 95);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function plainPng(int $width = 300, int $height = 200): string
    {
        $image = $this->markedImage($width, $height);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * JPEG com APP1 EXIF: Orientation e um GPS IFD (GPSLatitudeRef = "S").
     */
    protected function jpegWithExif(int $width = 300, int $height = 200, int $orientation = 1): string
    {
        $jpeg = $this->plainJpeg($width, $height);

        $gpsIfdOffset = 8 + 2 + 2 * 12 + 4;
        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 2)
            .pack('nnN', 0x0112, 3, 1).pack('nn', $orientation, 0)
            .pack('nnN', 0x8825, 4, 1).pack('N', $gpsIfdOffset)
            .pack('N', 0)
            .pack('n', 1)
            .pack('nnN', 0x0001, 2, 2)."S\0\0\0"
            .pack('N', 0);

        $app1 = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    /**
     * Cor do pixel (r, g, b) de uma imagem em bytes.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    protected function pixelAt(string $bytes, int $x, int $y): array
    {
        $image = imagecreatefromstring($bytes);
        $rgb = imagecolorat($image, $x, $y);
        imagedestroy($image);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    /**
     * Canto (tl, tr, bl, br) onde está o bloco vermelho.
     */
    protected function redCorner(string $bytes): string
    {
        [$width, $height] = getimagesizefromstring($bytes);
        $inset = (int) round(min($width, $height) * 0.08);
        $corners = [
            'tl' => [$inset, $inset],
            'tr' => [$width - $inset - 1, $inset],
            'bl' => [$inset, $height - $inset - 1],
            'br' => [$width - $inset - 1, $height - $inset - 1],
        ];

        foreach ($corners as $name => [$x, $y]) {
            [$r, $g, $b] = $this->pixelAt($bytes, $x, $y);

            if ($r > 200 && $g < 90 && $b < 90) {
                return $name;
            }
        }

        return 'none';
    }

    private function markedImage(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle(
            $image,
            0,
            0,
            (int) ($width * 0.25),
            (int) ($height * 0.25),
            (int) imagecolorallocate($image, 255, 0, 0),
        );

        return $image;
    }
}
