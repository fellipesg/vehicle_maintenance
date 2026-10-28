<?php

namespace Tests\Unit;

use App\Services\Maintenance\MaintenancePhotoImageProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\BuildsPhotoFixtures;
use Tests\TestCase;

/**
 * Os dois caminhos (Imagick e GD) precisam funcionar: não sabemos qual o ambiente de produção traz.
 */
class MaintenancePhotoImageProcessorTest extends TestCase
{
    use BuildsPhotoFixtures;

    /**
     * @return array<string, array{0: string}>
     */
    public static function drivers(): array
    {
        return [
            'gd' => [MaintenancePhotoImageProcessor::DRIVER_GD],
            'imagick' => [MaintenancePhotoImageProcessor::DRIVER_IMAGICK],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function orientations(): array
    {
        $cases = [];

        foreach ([1 => 'tl', 2 => 'tr', 3 => 'br', 4 => 'bl', 5 => 'tl', 6 => 'tr', 7 => 'br', 8 => 'bl'] as $orientation => $corner) {
            foreach (array_keys(self::drivers()) as $driver) {
                $cases["{$driver} orientation {$orientation}"] = [$driver, $orientation, $corner];
            }
        }

        return $cases;
    }

    private function processor(string $driver): MaintenancePhotoImageProcessor
    {
        if ($driver === MaintenancePhotoImageProcessor::DRIVER_IMAGICK && ! extension_loaded('imagick')) {
            $this->refuseOrSkipWithoutImagick();
        }

        return new MaintenancePhotoImageProcessor($driver);
    }

    /**
     * Pular em silêncio é o que escondeu que nenhum ambiente nosso exercitava o Imagick: sem a extensão,
     * os catorze casos do driver pulavam e o PHPUnit ainda saía com 0. Na máquina de quem não tem a
     * extensão o pulo continua; onde o ambiente se diz CI, a ausência é defeito de imagem e falha.
     */
    private function refuseOrSkipWithoutImagick(): never
    {
        $ci = getenv('CI');

        if (is_string($ci) && ! in_array(strtolower($ci), ['', '0', 'false'], true)) {
            $this->fail(
                'Extensão imagick ausente no CI. Ela está na lista de extensions do .github/workflows/ci.yml: '
                .'se o job passou sem ela, a imagem mudou e o caminho Imagick voltaria a não ser testado.',
            );
        }

        $this->markTestSkipped('Extensão imagick não instalada.');
    }

    public function test_driver_defaults_to_imagick_only_when_the_extension_exists(): void
    {
        $expected = extension_loaded('imagick')
            ? MaintenancePhotoImageProcessor::DRIVER_IMAGICK
            : MaintenancePhotoImageProcessor::DRIVER_GD;

        $this->assertSame($expected, (new MaintenancePhotoImageProcessor)->driver());
    }

    #[DataProvider('drivers')]
    public function test_master_is_a_jpeg_without_exif_or_gps(string $driver): void
    {
        $master = $this->processor($driver)->master($this->jpegWithExif(300, 200, 1));

        $this->assertSame('image/jpeg', getimagesizefromstring($master['contents'])['mime']);
        $this->assertStringNotContainsString('Exif', $master['contents']);
        $this->assertArrayNotHasKey('GPSLatitudeRef', $this->exifOf($master['contents']));
        $this->assertSame(300, $master['width']);
        $this->assertSame(200, $master['height']);
        $this->assertSame(strlen($master['contents']), $master['bytes']);
    }

    #[DataProvider('orientations')]
    public function test_master_applies_the_exif_orientation_to_the_pixels(string $driver, int $orientation, string $expectedCorner): void
    {
        $master = $this->processor($driver)->master($this->jpegWithExif(300, 200, $orientation));

        $this->assertSame($expectedCorner, $this->redCorner($master['contents']));
        $swapped = $orientation >= 5;
        $this->assertSame($swapped ? 200 : 300, $master['width']);
        $this->assertSame($swapped ? 300 : 200, $master['height']);
        $this->assertArrayNotHasKey('Orientation', $this->exifOf($master['contents']));
    }

    #[DataProvider('drivers')]
    public function test_master_is_limited_to_2048_on_the_long_side_and_never_upscales(string $driver): void
    {
        $processor = $this->processor($driver);

        $big = $processor->master($this->plainJpeg(4096, 3072));
        $this->assertSame(2048, $big['width']);
        $this->assertSame(1536, $big['height']);

        $small = $processor->master($this->plainJpeg(400, 300));
        $this->assertSame([400, 300], [$small['width'], $small['height']]);
    }

    #[DataProvider('drivers')]
    public function test_png_becomes_a_jpeg_master(string $driver): void
    {
        $master = $this->processor($driver)->master($this->plainPng(300, 200));

        $this->assertSame('image/jpeg', getimagesizefromstring($master['contents'])['mime']);
    }

    #[DataProvider('drivers')]
    public function test_dominant_color_is_a_hex_string(string $driver): void
    {
        $master = $this->processor($driver)->master($this->plainJpeg(300, 200));

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $master['dominant_color']);
    }

    #[DataProvider('drivers')]
    public function test_variants_have_the_expected_size_and_format(string $driver): void
    {
        $processor = $this->processor($driver);
        $master = $processor->master($this->plainJpeg(2400, 1800))['contents'];

        $thumb = $processor->thumb($master);
        $display = $processor->display($master);

        $expectedMime = $processor->supportsWebp() ? 'image/webp' : 'image/jpeg';
        $this->assertSame($expectedMime, $thumb['mime']);
        $this->assertSame($processor->supportsWebp() ? 'webp' : 'jpg', $thumb['extension']);
        $this->assertSame($expectedMime, getimagesizefromstring($thumb['contents'])['mime']);
        $this->assertSame([320, 240], array_slice(getimagesizefromstring($thumb['contents']), 0, 2));
        $this->assertSame([1280, 960], array_slice(getimagesizefromstring($display['contents']), 0, 2));
        $this->assertLessThan(strlen($display['contents']), strlen($thumb['contents']));
    }

    public function test_variants_fall_back_to_jpeg_when_webp_is_unavailable(): void
    {
        $processor = new MaintenancePhotoImageProcessor(MaintenancePhotoImageProcessor::DRIVER_GD);
        $result = (new \ReflectionMethod($processor, 'variantResult'))->invoke($processor, 'bytes', false);

        $this->assertSame(['contents' => 'bytes', 'extension' => 'jpg', 'mime' => 'image/jpeg'], $result);
    }

    #[DataProvider('drivers')]
    public function test_undecodable_bytes_throw(string $driver): void
    {
        $this->expectException(RuntimeException::class);

        $this->processor($driver)->master('isto não é uma imagem');
    }

    /**
     * @return array<string, mixed>
     */
    private function exifOf(string $jpeg): array
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Extensão exif não instalada.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $jpeg);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        return is_array($exif) ? $exif : [];
    }
}
