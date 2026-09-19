<?php

/**
 * Brand asset cropper — reads brand/grok-imagine/*.jpg and writes PNGs on exact #0B1C2C.
 * Run: php scripts/crop-brand-assets.php
 */

declare(strict_types=1);

const NAVY = [11, 28, 44]; // #0B1C2C
const TEAL = [46, 196, 182]; // #2EC4B6

$root = dirname(__DIR__, 2);
$sourceDir = $root.'/brand/grok-imagine';
$outBackend = dirname(__DIR__).'/public/images/brand';
$outFrontend = $root.'/frontend/assets/brand';

foreach ([$outBackend, $outFrontend] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

if (in_array('--og-from-lockup', $argv ?? [], true)) {
    $stackedPath = "{$outBackend}/lockup-stacked.png";
    $stacked = loadPng($stackedPath);
    $og = buildOgImage($stacked);
    imagedestroy($stacked);

    foreach (['og-image.png', 'og-preview.png'] as $filename) {
        savePng($og, "{$outBackend}/{$filename}");
        if (is_dir($outFrontend)) {
            savePng($og, "{$outFrontend}/{$filename}");
        }
        echo "Wrote {$filename}\n";
    }

    imagedestroy($og);
    echo "Done.\n";
    exit(0);
}

if (! is_dir($sourceDir)) {
    fwrite(STDERR, "Missing source dir: {$sourceDir}\n");
    exit(1);
}

$sheet2 = loadJpeg("{$sourceDir}/sheet-2-refined.jpg");
$sheet3 = loadJpeg("{$sourceDir}/sheet-3-abcd.jpg");

$stacked = crop($sheet3, 24, 132, 228, 396, solidNavyBg: true);

$crops = [
    'lockup-horizontal.png' => crop($sheet2, 517, 245, 447, 108, solidNavyBg: true),
    'lockup-horizontal-tagline.png' => crop($sheet2, 72, 79, 486, 127, solidNavyBg: true),
    // Skip the A/B caption row above each stacked lockup on sheet-3.
    'lockup-stacked.png' => $stacked,
    'lockup-stacked-bar.png' => crop($sheet3, 272, 132, 228, 396, solidNavyBg: true),
    'og-image.png' => buildOgImage(cloneGd($stacked)),
];

foreach ($crops as $filename => $image) {
    savePng($image, "{$outBackend}/{$filename}");
    savePng($image, "{$outFrontend}/{$filename}");
    if ($filename === 'og-image.png') {
        savePng($image, "{$outBackend}/og-preview.png");
        savePng($image, "{$outFrontend}/og-preview.png");
        echo "Wrote og-preview.png\n";
    }
    imagedestroy($image);
    echo "Wrote {$filename}\n";
}

$appIcon = renderRevisalogAppIcon(1024);
savePng($appIcon, "{$outBackend}/app-icon.png");
savePng($appIcon, "{$outFrontend}/app-icon.png");
imagedestroy($appIcon);
echo "Wrote app-icon.png (vector master)\n";

copy("{$outBackend}/lockup-horizontal.png", dirname(__DIR__).'/public/images/revisalog-logo.png');
copy("{$outBackend}/lockup-horizontal.png", "{$outBackend}/revisalog-logo.png");
echo "Wrote revisalog-logo.png alias\n";

$icon = loadPng("{$outBackend}/app-icon.png");
$favicon = resize($icon, 32, 32);
savePng($favicon, dirname(__DIR__).'/public/favicon.png');
savePng($favicon, "{$outBackend}/favicon.png");
imagedestroy($favicon);

$appleTouch = resize($icon, 180, 180);
savePng($appleTouch, dirname(__DIR__).'/public/apple-touch-icon.png');
savePng($appleTouch, "{$outBackend}/apple-touch-icon.png");
imagedestroy($appleTouch);
imagedestroy($icon);

generateAppIcons("{$outBackend}/app-icon.png", $root.'/frontend');

echo "Done.\n";

function loadJpeg(string $path): \GdImage
{
    $image = imagecreatefromjpeg($path);
    if ($image === false) {
        throw new RuntimeException("Cannot load JPEG: {$path}");
    }

    return $image;
}

function loadPng(string $path): \GdImage
{
    $image = imagecreatefrompng($path);
    if ($image === false) {
        throw new RuntimeException("Cannot load PNG: {$path}");
    }

    return $image;
}

function crop(\GdImage $source, int $x, int $y, int $w, int $h, bool $solidNavyBg = false): \GdImage
{
    $dest = imagecreatetruecolor($w, $h);
    imagecopy($dest, $source, 0, 0, $x, $y, $w, $h);

    if ($solidNavyBg) {
        return compositeOnExactNavy($dest);
    }

    return $dest;
}

/**
 * Replace Grok-sheet navy (any dark blue plate) with exact #0B1C2C; keep logo pixels.
 */
function compositeOnExactNavy(\GdImage $image): \GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    $dest = imagecreatetruecolor($w, $h);
    $navy = imagecolorallocate($dest, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($dest, 0, 0, $navy);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($image, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            if (! isSheetBackground($r, $g, $b)) {
                imagesetpixel($dest, $x, $y, imagecolorallocate($dest, $r, $g, $b));
            }
        }
    }

    imagedestroy($image);

    return $dest;
}

function isForegroundPixel(int $r, int $g, int $b): bool
{
    if ($r > 120 && $g > 120 && $b > 120) {
        return true;
    }

    if ($g > 90 && $b > 70 && $g > $r + 15) {
        return true;
    }

    return ($r + $g + $b) > 150;
}

function isSheetBackground(int $r, int $g, int $b): bool
{
    if (isForegroundPixel($r, $g, $b)) {
        return false;
    }

    $sum = $r + $g + $b;

    if (abs($r - NAVY[0]) < 40 && abs($g - NAVY[1]) < 40 && abs($b - NAVY[2]) < 40) {
        return true;
    }

    if ($b >= $g - 5 && $r < 55 && $g < 80 && $sum < 115) {
        return true;
    }

    return false;
}

function cloneGd(\GdImage $source): \GdImage
{
    $copy = imagecreatetruecolor(imagesx($source), imagesy($source));
    imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

    return $copy;
}

function trimToForeground(\GdImage $image, int $pad = 8): \GdImage
{
    $width = imagesx($image);
    $height = imagesy($image);
    $minX = $width;
    $minY = $height;
    $maxX = 0;
    $maxY = 0;
    $found = false;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($image, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            if (! isForegroundPixel($r, $g, $b)) {
                continue;
            }

            $found = true;
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }
    }

    if (! $found) {
        return cloneGd($image);
    }

    $x0 = max(0, $minX - $pad);
    $y0 = max(0, $minY - $pad);
    $x1 = min($width - 1, $maxX + $pad);
    $y1 = min($height - 1, $maxY + $pad);
    $cropW = $x1 - $x0 + 1;
    $cropH = $y1 - $y0 + 1;

    $dest = imagecreatetruecolor($cropW, $cropH);
    imagecopy($dest, $image, 0, 0, $x0, $y0, $cropW, $cropH);

    return $dest;
}

function prepareStackedLockupForOg(\GdImage $stackedLockup): \GdImage
{
    $width = imagesx($stackedLockup);
    $height = imagesy($stackedLockup);
    $skipTop = (int) floor($height * 0.32);
    $withoutCaption = imagecreatetruecolor($width, $height - $skipTop);
    imagecopy($withoutCaption, $stackedLockup, 0, 0, 0, $skipTop, $width, $height - $skipTop);

    $trimmed = trimToForeground($withoutCaption, 10);
    imagedestroy($withoutCaption);

    return compositeOnExactNavy($trimmed);
}

function buildOgImage(\GdImage $stackedLockup): \GdImage
{
    $canvasW = 1200;
    $canvasH = 630;
    $square = min($canvasW, $canvasH);
    $lockup = prepareStackedLockupForOg($stackedLockup);
    $lockupW = imagesx($lockup);
    $lockupH = imagesy($lockup);

    $maxSide = (int) round($square * 0.90);
    $scale = min($maxSide / $lockupW, $maxSide / $lockupH);
    $targetW = (int) round($lockupW * $scale);
    $targetH = (int) round($lockupH * $scale);

    $og = imagecreatetruecolor($canvasW, $canvasH);
    $navy = imagecolorallocate($og, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($og, 0, 0, $navy);

    $dstX = (int) (($canvasW - $targetW) / 2);
    $dstY = (int) (($canvasH - $targetH) / 2);
    imagecopyresampled($og, $lockup, $dstX, $dstY, 0, 0, $targetW, $targetH, $lockupW, $lockupH);
    imagedestroy($lockup);

    return $og;
}

function resize(\GdImage $source, int $w, int $h): \GdImage
{
    $dest = imagecreatetruecolor($w, $h);
    $navy = imagecolorallocate($dest, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($dest, 0, 0, $navy);
    imagealphablending($dest, true);
    imagecopyresampled($dest, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));

    return $dest;
}

/**
 * Center the mark on navy with inset so iOS squircle / Android adaptive masks do not clip it.
 *
 * @param  float  $contentRatio  Fraction of the canvas the source should occupy (0–1).
 */
/**
 * Variation D includes a teal rounded-rect frame. Android adaptive icons mask the
 * foreground again, so keep only the inner odometer for ic_launcher_foreground.
 */
function recenterMark(\GdImage $image, int $shiftY = 0, bool $horizontalOnly = false): \GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    $sumX = 0;
    $sumY = 0;
    $n = 0;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($image, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            if (isForegroundPixel($r, $g, $b)) {
                $sumX += $x;
                $sumY += $y;
                $n++;
            }
        }
    }

    if ($n === 0) {
        return $image;
    }

    $dx = (int) round(($w / 2) - ($sumX / $n));
    $dy = $horizontalOnly
        ? $shiftY
        : (int) round(($h / 2) - ($sumY / $n)) + $shiftY;

    $dest = imagecreatetruecolor($w, $h);
    $navy = imagecolorallocate($dest, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($dest, 0, 0, $navy);

    $srcX = max(0, -$dx);
    $srcY = max(0, -$dy);
    $dstX = max(0, $dx);
    $dstY = max(0, $dy);
    $copyW = $w - abs($dx);
    $copyH = $h - abs($dy);

    if ($copyW > 0 && $copyH > 0) {
        imagecopy($dest, $image, $dstX, $dstY, $srcX, $srcY, $copyW, $copyH);
    }

    imagedestroy($image);

    return $dest;
}

/**
 * Drop the teal rounded-rect frame; higher inset removes more border residue.
 */
function innerAppMark(\GdImage $appIcon, float $insetRatio = 0.15): \GdImage
{
    $w = imagesx($appIcon);
    $h = imagesy($appIcon);
    $inset = (int) round(min($w, $h) * $insetRatio);
    $innerW = $w - (2 * $inset);
    $innerH = $h - (2 * $inset);

    $cropped = imagecreatetruecolor($innerW, $innerH);
    imagecopy($cropped, $appIcon, 0, 0, $inset, $inset, $innerW, $innerH);

    return compositeOnExactNavy($cropped);
}

function isResidualFramePixel(int $r, int $g, int $b): bool
{
    if ($r > 210 && $g > 210 && $b > 210) {
        return false;
    }

    if ($g > 75 && $b > 55 && $g >= $r + 8 && ($r + $g + $b) < 430) {
        return true;
    }

    $sum = $r + $g + $b;
    if ($sum < 95 || $sum > 400) {
        return false;
    }

    $spread = max($r, $g, $b) - min($r, $g, $b);

    return $spread < 85 && $b >= $g - 25 && $r < 140;
}

/**
 * Remove anti-aliased scraps of the old rounded frame (often top-left on iOS squircle).
 */
function stripResidualFrame(\GdImage $image): \GdImage
{
    $w = imagesx($image);
    $h = imagesy($image);
    $edge = (int) round(min($w, $h) * 0.11);
    $corner = (int) round(min($w, $h) * 0.26);
    $navy = imagecolorallocate($image, NAVY[0], NAVY[1], NAVY[2]);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $inCorner = $x < $corner && $y < $corner;
            $nearEdge = $x < $edge || $y < $edge || $x >= $w - $edge || $y >= $h - $edge;

            if (! $inCorner && ! $nearEdge) {
                continue;
            }

            $rgba = imagecolorat($image, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            if (! isForegroundPixel($r, $g, $b)) {
                continue;
            }

            if ($inCorner || isResidualFramePixel($r, $g, $b)) {
                imagesetpixel($image, $x, $y, $navy);
            }
        }
    }

    return $image;
}

function resizeWithSafeZone(\GdImage $source, int $size, float $contentRatio = 0.84, int $verticalShift = 0): \GdImage
{
    $dest = imagecreatetruecolor($size, $size);
    $navy = imagecolorallocate($dest, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($dest, 0, 0, $navy);

    $content = max(1, (int) round($size * $contentRatio));
    $offsetX = (int) round(($size - $content) / 2);
    $offsetY = $offsetX + $verticalShift;

    imagecopyresampled(
        $dest,
        $source,
        $offsetX,
        $offsetY,
        0,
        0,
        $content,
        $content,
        imagesx($source),
        imagesy($source),
    );

    return $dest;
}

/**
 * Android launcher masks crop the bottom of adaptive icons; scale down and bias up.
 */
function buildAdaptiveLauncherForeground(\GdImage $appIcon): \GdImage
{
    return resize($appIcon, 432, 432);
}

/**
 * Variation D odometer (sheet-3 / home-screen icon): thin teal arc, thin needle, three dots.
 * Same mark as the existing logo — only the scale inside the 1024 square changes.
 */
function renderRevisalogAppIcon(int $size = 1024): \GdImage
{
    $img = imagecreatetruecolor($size, $size);
    $navy = imagecolorallocate($img, NAVY[0], NAVY[1], NAVY[2]);
    $teal = imagecolorallocate($img, TEAL[0], TEAL[1], TEAL[2]);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $navy);

    $cx = $size / 2;
    $radius = $size * 0.43;
    $topInset = $size * 0.135;
    $cy = $topInset + $radius;
    $arcStart = 158.0;
    $arcEnd = 22.0;
    $arcEndDrawn = $arcEnd < $arcStart ? $arcEnd + 360.0 : $arcEnd;

    drawSmoothArc($img, $cx, $cy, $radius, $arcStart, $arcEnd, $teal, $size * 0.020);

    for ($deg = $arcStart + 22; $deg <= $arcEndDrawn - 22; $deg += 20) {
        [$x1, $y1] = polarToXY($cx, $cy, $radius - $size * 0.018, $deg);
        [$x2, $y2] = polarToXY($cx, $cy, $radius - $size * 0.040, $deg);
        drawSmoothLine($img, $x1, $y1, $x2, $y2, $white, $size * 0.005);
    }

    [$nx, $ny] = polarToXY($cx, $cy, $radius * 0.66, 306.0);
    drawSmoothLine($img, $cx, $cy, $nx, $ny, $white, $size * 0.026);

    $hub = (int) max(8, round($size * 0.032));
    imagefilledellipse($img, (int) round($cx), (int) round($cy), $hub, $hub, $white);

    $dotD = (int) max(5, round($size * 0.022));
    $dotY = $cy + $radius * 0.50;
    $dotGap = $size * 0.048;
    foreach ([-1.0, 0.0, 1.0] as $offset) {
        imagefilledellipse(
            $img,
            (int) round($cx + ($offset * $dotGap)),
            (int) round($dotY),
            $dotD,
            $dotD,
            $teal,
        );
    }

    return $img;
}

/**
 * @return array{0: float, 1: float}
 */
function polarToXY(float $cx, float $cy, float $radius, float $degClockwiseFromEast): array
{
    $rad = deg2rad($degClockwiseFromEast);

    return [
        $cx + cos($rad) * $radius,
        $cy + sin($rad) * $radius,
    ];
}

function drawSmoothArc(
    \GdImage $img,
    float $cx,
    float $cy,
    float $radius,
    float $startDeg,
    float $endDeg,
    int $color,
    float $thickness,
): void {
    if ($endDeg < $startDeg) {
        $endDeg += 360;
    }

    $sweep = $endDeg - $startDeg;
    $steps = max(48, (int) ceil($sweep * $radius / 1.6));
    $diameter = max(2, (int) round($thickness));

    for ($i = 0; $i <= $steps; $i++) {
        [$x, $y] = polarToXY($cx, $cy, $radius, $startDeg + ($sweep * $i / $steps));
        imagefilledellipse($img, (int) round($x), (int) round($y), $diameter, $diameter, $color);
    }
}

function drawSmoothLine(
    \GdImage $img,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    int $color,
    float $thickness,
): void {
    $length = hypot($x2 - $x1, $y2 - $y1);
    $steps = max(2, (int) ceil($length));
    $diameter = max(2, (int) round($thickness));

    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps;
        imagefilledellipse(
            $img,
            (int) round($x1 + (($x2 - $x1) * $t)),
            (int) round($y1 + (($y2 - $y1) * $t)),
            $diameter,
            $diameter,
            $color,
        );
    }
}

function savePng(\GdImage $image, string $path): void
{
    if (! imagepng($image, $path, 6)) {
        throw new RuntimeException("Cannot write PNG: {$path}");
    }
}

function generateAppIcons(string $sourcePath, string $frontendRoot): void
{
    $source = loadPng($sourcePath);

    $iosSizes = [
        'Icon-App-20x20@1x.png' => 20,
        'Icon-App-20x20@2x.png' => 40,
        'Icon-App-20x20@3x.png' => 60,
        'Icon-App-29x29@1x.png' => 29,
        'Icon-App-29x29@2x.png' => 58,
        'Icon-App-29x29@3x.png' => 87,
        'Icon-App-40x40@1x.png' => 40,
        'Icon-App-40x40@2x.png' => 80,
        'Icon-App-40x40@3x.png' => 120,
        'Icon-App-60x60@2x.png' => 120,
        'Icon-App-60x60@3x.png' => 180,
        'Icon-App-76x76@1x.png' => 76,
        'Icon-App-76x76@2x.png' => 152,
        'Icon-App-83.5x83.5@2x.png' => 167,
        'Icon-App-1024x1024@1x.png' => 1024,
    ];

    $iosDir = "{$frontendRoot}/ios/Runner/Assets.xcassets/AppIcon.appiconset";
    foreach ($iosSizes as $filename => $size) {
        $icon = resize($source, $size, $size);
        savePng($icon, "{$iosDir}/{$filename}");
        imagedestroy($icon);
    }

    $macSizes = [
        'app_icon_16.png' => 16,
        'app_icon_32.png' => 32,
        'app_icon_64.png' => 64,
        'app_icon_128.png' => 128,
        'app_icon_256.png' => 256,
        'app_icon_512.png' => 512,
        'app_icon_1024.png' => 1024,
    ];

    $macDir = "{$frontendRoot}/macos/Runner/Assets.xcassets/AppIcon.appiconset";
    foreach ($macSizes as $filename => $size) {
        $icon = resize($source, $size, $size);
        savePng($icon, "{$macDir}/{$filename}");
        imagedestroy($icon);
    }

    $androidSizes = [
        'mipmap-mdpi' => 48,
        'mipmap-hdpi' => 72,
        'mipmap-xhdpi' => 96,
        'mipmap-xxhdpi' => 144,
        'mipmap-xxxhdpi' => 192,
    ];

    foreach ($androidSizes as $folder => $size) {
        $dir = "{$frontendRoot}/android/app/src/main/res/{$folder}";
        if (! is_dir($dir)) {
            continue;
        }
        $icon = resize($source, $size, $size);
        savePng($icon, "{$dir}/ic_launcher.png");
        savePng($icon, "{$dir}/ic_launcher_round.png");
        imagedestroy($icon);
    }

    $adaptiveDir = "{$frontendRoot}/android/app/src/main/res/drawable";
    if (is_dir($adaptiveDir)) {
        $foreground = buildAdaptiveLauncherForeground($source);
        savePng($foreground, "{$adaptiveDir}/ic_launcher_foreground.png");
        savePng($foreground, "{$adaptiveDir}/ic_launcher.png");
        imagedestroy($foreground);
    }

    imagedestroy($source);
    echo "Generated platform app icons\n";
}
