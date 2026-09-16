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

if (! is_dir($sourceDir)) {
    fwrite(STDERR, "Missing source dir: {$sourceDir}\n");
    exit(1);
}

foreach ([$outBackend, $outFrontend] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

$sheet2 = loadJpeg("{$sourceDir}/sheet-2-refined.jpg");
$sheet3 = loadJpeg("{$sourceDir}/sheet-3-abcd.jpg");

$crops = [
    // Variation D is the rounded app-icon card under the "D" label — not the label itself.
    // (772, 108, 248, 248) captured the D caption and the top of the mark only.
    'app-icon.png' => crop($sheet3, 782, 250, 200, 200, solidNavyBg: true),
    'lockup-horizontal.png' => crop($sheet2, 517, 245, 447, 108, solidNavyBg: true),
    'lockup-horizontal-tagline.png' => crop($sheet2, 72, 79, 486, 127, solidNavyBg: true),
    // Skip the A/B caption row above each stacked lockup on sheet-3.
    'lockup-stacked.png' => crop($sheet3, 24, 132, 228, 396, solidNavyBg: true),
    'lockup-stacked-bar.png' => crop($sheet3, 272, 132, 228, 396, solidNavyBg: true),
    'og-image.png' => buildOgImage($sheet2),
];

foreach ($crops as $filename => $image) {
    if ($filename === 'app-icon.png') {
        $image = recenterMark($image);
    }

    savePng($image, "{$outBackend}/{$filename}");
    savePng($image, "{$outFrontend}/{$filename}");
    imagedestroy($image);
    echo "Wrote {$filename}\n";
}

copy("{$outBackend}/lockup-horizontal.png", dirname(__DIR__).'/public/images/revisalog-logo.png');
echo "Wrote revisalog-logo.png alias\n";

$icon = loadPng("{$outBackend}/app-icon.png");
$favicon = resize($icon, 32, 32);
savePng($favicon, dirname(__DIR__).'/public/favicon.png');
imagedestroy($favicon);

$appleTouch = resize($icon, 180, 180);
savePng($appleTouch, dirname(__DIR__).'/public/apple-touch-icon.png');
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

function buildOgImage(\GdImage $sheet2): \GdImage
{
    $lockup = crop($sheet2, 72, 79, 486, 127, solidNavyBg: true);
    $lw = imagesx($lockup);
    $lh = imagesy($lockup);

    $og = imagecreatetruecolor(1200, 630);
    $navy = imagecolorallocate($og, NAVY[0], NAVY[1], NAVY[2]);
    imagefill($og, 0, 0, $navy);

    $targetH = 280;
    $targetW = (int) round($lw * ($targetH / $lh));
    $dstX = (int) ((1200 - $targetW) / 2);
    $dstY = (int) ((630 - $targetH) / 2);

    imagecopyresampled($og, $lockup, $dstX, $dstY, 0, 0, $targetW, $targetH, $lw, $lh);
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

function innerAppMark(\GdImage $appIcon): \GdImage
{
    $w = imagesx($appIcon);
    $h = imagesy($appIcon);
    $inset = (int) round(min($w, $h) * 0.15);
    $innerW = $w - (2 * $inset);
    $innerH = $h - (2 * $inset);

    $cropped = imagecreatetruecolor($innerW, $innerH);
    imagecopy($cropped, $appIcon, 0, 0, $inset, $inset, $innerW, $innerH);

    return compositeOnExactNavy($cropped);
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
    $mark = recenterMark(innerAppMark($appIcon));
    $sized = resizeWithSafeZone($mark, 432, 0.70, verticalShift: -4);
    imagedestroy($mark);

    return recenterMark($sized, shiftY: -9, horizontalOnly: true);
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
        $icon = resizeWithSafeZone($source, $size, 0.84);
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
        $icon = resizeWithSafeZone($source, $size, 0.84);
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
        $icon = resizeWithSafeZone($source, $size, 0.84);
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
