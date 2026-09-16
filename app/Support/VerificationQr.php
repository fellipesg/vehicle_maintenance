<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class VerificationQr
{
    /**
     * @return array<int, array<int, int>>
     */
    public static function matrix(string $url): array
    {
        $qrCode = Encoder::encode($url, ErrorCorrectionLevel::L());
        $matrix = $qrCode->getMatrix();
        $width = $matrix->getWidth();
        $rows = [];

        for ($y = 0; $y < $width; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = $matrix->get($x, $y) ? 1 : 0;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public static function svg(string $url, int $size = 96): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }
}
