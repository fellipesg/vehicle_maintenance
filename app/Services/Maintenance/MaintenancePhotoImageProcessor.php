<?php

namespace App\Services\Maintenance;

use Imagick;
use ImagickException;
use ImagickPixel;
use RuntimeException;

/**
 * Manipulação de imagem das fotos de manutenção, sem dependência nova: Imagick quando a extensão
 * existe, GD caso contrário (não sabemos se o ambiente de produção traz Imagick, então os dois
 * caminhos precisam funcionar e têm teste).
 *
 * - master: lado maior até 2048px, JPEG q85, orientação do EXIF aplicada nos pixels e metadados
 *   (EXIF/GPS, comentários) removidos. A foto do celular traz a localização de quem fotografou,
 *   então nada que saia daqui carrega EXIF. O Imagick preserva o perfil de cor ICC (Display P3 do
 *   iPhone); o GD não consegue e grava sem perfil.
 * - thumb (320px, WebP q70) e display (1280px, WebP q80) saem do master, nunca do original. Sem
 *   suporte a WebP no ambiente o arquivo sai em JPEG e o chamador usa a extensão devolvida.
 *
 * Nunca amplia: imagem menor que o limite mantém o tamanho.
 */
final class MaintenancePhotoImageProcessor
{
    public const DRIVER_IMAGICK = 'imagick';

    public const DRIVER_GD = 'gd';

    public const MASTER_MAX_SIDE = 2048;

    public const MASTER_QUALITY = 85;

    public const THUMB_MAX_SIDE = 320;

    public const THUMB_QUALITY = 70;

    public const DISPLAY_MAX_SIDE = 1280;

    public const DISPLAY_QUALITY = 80;

    /**
     * Teto de pixels decodificados (uma bomba de descompressão cabe em poucos KB de arquivo).
     */
    private const MAX_PIXELS = 100_000_000;

    /**
     * @param  string|null  $driver  'imagick' ou 'gd'; nulo escolhe o Imagick quando a extensão existe
     */
    public function __construct(private readonly ?string $driver = null) {}

    public function driver(): string
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        return extension_loaded('imagick') && class_exists(Imagick::class)
            ? self::DRIVER_IMAGICK
            : self::DRIVER_GD;
    }

    public function supportsWebp(): bool
    {
        if ($this->driver() === self::DRIVER_IMAGICK) {
            return Imagick::queryFormats('WEBP') !== [];
        }

        return function_exists('imagewebp');
    }

    /**
     * @return array{contents: string, width: int, height: int, bytes: int, dominant_color: string}
     *
     * @throws RuntimeException quando o arquivo não é uma imagem que o servidor consiga decodificar
     */
    public function master(string $bytes): array
    {
        $this->assertDecodable($bytes);

        return $this->driver() === self::DRIVER_IMAGICK
            ? $this->imagickMaster($bytes)
            : $this->gdMaster($bytes);
    }

    /**
     * Miniatura de lista (320px, WebP q70).
     *
     * @return array{contents: string, extension: string, mime: string}
     */
    public function thumb(string $masterBytes): array
    {
        return $this->variant($masterBytes, self::THUMB_MAX_SIDE, self::THUMB_QUALITY);
    }

    /**
     * Imagem de tela (1280px, WebP q80).
     *
     * @return array{contents: string, extension: string, mime: string}
     */
    public function display(string $masterBytes): array
    {
        return $this->variant($masterBytes, self::DISPLAY_MAX_SIDE, self::DISPLAY_QUALITY);
    }

    /**
     * @return array{contents: string, extension: string, mime: string}
     */
    public function variant(string $masterBytes, int $maxSide, int $quality): array
    {
        $this->assertDecodable($masterBytes);

        return $this->driver() === self::DRIVER_IMAGICK
            ? $this->imagickVariant($masterBytes, $maxSide, $quality)
            : $this->gdVariant($masterBytes, $maxSide, $quality);
    }

    private function assertDecodable(string $bytes): void
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            throw new RuntimeException('Unable to decode maintenance photo.');
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Maintenance photo has too many pixels.');
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fit(int $width, int $height, int $maxSide): array
    {
        $longSide = max($width, $height);

        if ($longSide <= $maxSide) {
            return [$width, $height];
        }

        $ratio = $maxSide / $longSide;

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    /* Imagick */

    /**
     * @return array{contents: string, width: int, height: int, bytes: int, dominant_color: string}
     */
    private function imagickMaster(string $bytes): array
    {
        $image = $this->imagickRead($bytes);

        try {
            $this->imagickApplyOrientation($image);
            $this->imagickFlatten($image);
            $this->imagickResize($image, self::MASTER_MAX_SIDE);
            $this->imagickStripKeepingColorProfile($image);

            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(self::MASTER_QUALITY);
            $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);

            $contents = $image->getImageBlob();

            return [
                'contents' => $contents,
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight(),
                'bytes' => strlen($contents),
                'dominant_color' => $this->imagickDominantColor($image),
            ];
        } catch (ImagickException $exception) {
            throw new RuntimeException('Unable to process maintenance photo: '.$exception->getMessage(), 0, $exception);
        } finally {
            $image->clear();
        }
    }

    /**
     * @return array{contents: string, extension: string, mime: string}
     */
    private function imagickVariant(string $masterBytes, int $maxSide, int $quality): array
    {
        $image = $this->imagickRead($masterBytes);

        try {
            $this->imagickResize($image, $maxSide);
            $this->imagickStripKeepingColorProfile($image);

            $webp = $this->supportsWebp();
            $image->setImageFormat($webp ? 'webp' : 'jpeg');
            $image->setImageCompressionQuality($quality);

            return $this->variantResult($image->getImageBlob(), $webp);
        } catch (ImagickException $exception) {
            throw new RuntimeException('Unable to process maintenance photo: '.$exception->getMessage(), 0, $exception);
        } finally {
            $image->clear();
        }
    }

    private function imagickRead(string $bytes): Imagick
    {
        try {
            $image = new Imagick;
            $image->readImageBlob($bytes);

            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
                $first = $image->getImage();
                $image->clear();
                $image = $first;
            }

            return $image;
        } catch (ImagickException $exception) {
            throw new RuntimeException('Unable to decode maintenance photo.', 0, $exception);
        }
    }

    private function imagickApplyOrientation(Imagick $image): void
    {
        $orientation = $image->getImageOrientation();

        if ($orientation === Imagick::ORIENTATION_UNDEFINED || $orientation === Imagick::ORIENTATION_TOPLEFT) {
            return;
        }

        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();

            return;
        }

        $background = new ImagickPixel('white');

        match ($orientation) {
            Imagick::ORIENTATION_TOPRIGHT => $image->flopImage(),
            Imagick::ORIENTATION_BOTTOMRIGHT => $image->rotateImage($background, 180),
            Imagick::ORIENTATION_BOTTOMLEFT => $image->flipImage(),
            Imagick::ORIENTATION_LEFTTOP => $this->imagickTranspose($image),
            Imagick::ORIENTATION_RIGHTTOP => $image->rotateImage($background, 90),
            Imagick::ORIENTATION_RIGHTBOTTOM => $this->imagickTransverse($image),
            Imagick::ORIENTATION_LEFTBOTTOM => $image->rotateImage($background, 270),
            default => null,
        };

        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    private function imagickTranspose(Imagick $image): void
    {
        $image->rotateImage(new ImagickPixel('white'), 90);
        $image->flopImage();
    }

    private function imagickTransverse(Imagick $image): void
    {
        $image->rotateImage(new ImagickPixel('white'), 270);
        $image->flopImage();
    }

    /**
     * PNG/WebP com transparência vira fundo branco: o master é JPEG.
     */
    private function imagickFlatten(Imagick $image): void
    {
        if (! in_array(strtoupper($image->getImageFormat()), ['PNG', 'WEBP', 'GIF'], true)) {
            return;
        }

        $image->setImageBackgroundColor(new ImagickPixel('white'));
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    }

    private function imagickResize(Imagick $image, int $maxSide): void
    {
        [$width, $height] = $this->fit($image->getImageWidth(), $image->getImageHeight(), $maxSide);

        if ($width !== $image->getImageWidth() || $height !== $image->getImageHeight()) {
            $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        }
    }

    /**
     * Tira EXIF, GPS e comentários, mas devolve o perfil ICC: sem ele uma foto Display P3 perde
     * saturação.
     */
    private function imagickStripKeepingColorProfile(Imagick $image): void
    {
        $icc = $image->getImageProfiles('icc', true)['icc'] ?? null;

        $image->stripImage();

        if (is_string($icc) && $icc !== '') {
            $image->profileImage('icc', $icc);
        }
    }

    private function imagickDominantColor(Imagick $image): string
    {
        $pixel = clone $image;
        $pixel->resizeImage(1, 1, Imagick::FILTER_BOX, 1);
        $color = $pixel->getImagePixelColor(0, 0)->getColor();
        $pixel->clear();

        return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
    }

    /* GD */

    /**
     * @return array{contents: string, width: int, height: int, bytes: int, dominant_color: string}
     */
    private function gdMaster(string $bytes): array
    {
        $source = $this->gdDecode($bytes);
        $source = $this->gdApplyOrientation($source, $this->exifOrientation($bytes));
        $image = $this->gdResize($source, self::MASTER_MAX_SIDE, flattenAlpha: true);

        $contents = $this->gdEncodeJpeg($image, self::MASTER_QUALITY);
        $result = [
            'contents' => $contents,
            'width' => imagesx($image),
            'height' => imagesy($image),
            'bytes' => strlen($contents),
            'dominant_color' => $this->gdDominantColor($image),
        ];

        imagedestroy($image);

        return $result;
    }

    /**
     * @return array{contents: string, extension: string, mime: string}
     */
    private function gdVariant(string $masterBytes, int $maxSide, int $quality): array
    {
        $image = $this->gdResize($this->gdDecode($masterBytes), $maxSide, flattenAlpha: true);

        $webp = $this->supportsWebp();
        $contents = $webp
            ? $this->capture(fn () => imagewebp($image, null, $quality))
            : $this->gdEncodeJpeg($image, $quality);

        imagedestroy($image);

        return $this->variantResult($contents, $webp);
    }

    private function gdDecode(string $bytes): \GdImage
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD extension is required to process maintenance photos.');
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException('Unable to decode maintenance photo.');
        }

        return $image;
    }

    /**
     * Orientação do EXIF (1-8) de um JPEG; 1 quando não há EXIF legível ou a extensão não existe.
     */
    private function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return 1;
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return 1;
        }

        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream, 'IFD0');
        fclose($stream);

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function gdApplyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        return match ($orientation) {
            2 => $this->gdFlip($image),
            3 => $this->gdRotate($image, 180),
            4 => $this->gdFlip($image, IMG_FLIP_VERTICAL),
            5 => $this->gdFlip($this->gdRotate($image, -90)),
            6 => $this->gdRotate($image, -90),
            7 => $this->gdFlip($this->gdRotate($image, 90)),
            8 => $this->gdRotate($image, 90),
            default => $image,
        };
    }

    private function gdFlip(\GdImage $image, int $mode = IMG_FLIP_HORIZONTAL): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    /**
     * @param  float  $degrees  sentido anti-horário (como o imagerotate)
     */
    private function gdRotate(\GdImage $image, float $degrees): \GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);

        if ($rotated === false) {
            throw new RuntimeException('Unable to rotate maintenance photo.');
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Devolve uma cópia truecolor no tamanho de destino, com fundo branco sob a transparência.
     * Consome (destrói) a imagem de origem.
     */
    private function gdResize(\GdImage $source, int $maxSide, bool $flattenAlpha): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        [$newWidth, $newHeight] = $this->fit($width, $height, $maxSide);

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($flattenAlpha) {
            imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        return $target;
    }

    private function gdEncodeJpeg(\GdImage $image, int $quality): string
    {
        imageinterlace($image, true);

        return $this->capture(fn () => imagejpeg($image, null, $quality));
    }

    private function gdDominantColor(\GdImage $image): string
    {
        $pixel = imagecreatetruecolor(1, 1);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
        $rgb = imagecolorat($pixel, 0, 0);
        imagedestroy($pixel);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }

    private function capture(callable $encode): string
    {
        ob_start();
        $encode();
        $contents = (string) ob_get_clean();

        if ($contents === '') {
            throw new RuntimeException('Unable to encode maintenance photo.');
        }

        return $contents;
    }

    /**
     * @return array{contents: string, extension: string, mime: string}
     */
    private function variantResult(string $contents, bool $webp): array
    {
        return [
            'contents' => $contents,
            'extension' => $webp ? 'webp' : 'jpg',
            'mime' => $webp ? 'image/webp' : 'image/jpeg',
        ];
    }
}
