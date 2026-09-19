<?php

namespace Tests\Unit;

use App\Support\AppStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppStorageCoversTest extends TestCase
{
    public function test_covers_url_uses_public_r2_base_when_configured(): void
    {
        Config::set('filesystems.covers_disk', 'r2');
        Config::set('filesystems.disks.r2', [
            'driver' => 's3',
            'visibility' => 'public',
            'url' => 'https://cdn.example.test/vehicle-maintenance',
        ]);

        putenv('VEHICLE_COVERS_DISK=r2');
        $_ENV['VEHICLE_COVERS_DISK'] = 'r2';

        $url = AppStorage::coversUrl('vehicle-covers/1_test.jpg');

        $this->assertSame(
            'https://cdn.example.test/vehicle-maintenance/vehicle-covers/1_test.jpg',
            $url
        );

        Config::set('filesystems.covers_disk', null);
    }

    public function test_brand_url_uses_public_r2_base_when_remote(): void
    {
        $this->configurePublicCoversDisk();

        $url = AppStorage::brandUrl('lockup-horizontal.png');

        $this->assertSame(
            'https://cdn.example.test/vehicle-maintenance/brand/revisalog/lockup-horizontal.png',
            $url
        );
    }

    public function test_brand_url_falls_back_to_local_asset_when_not_remote(): void
    {
        Config::set('filesystems.covers_disk', 'public');
        Storage::fake('public');

        $url = AppStorage::brandUrl('lockup-horizontal.png');

        $this->assertStringContainsString('/images/brand/lockup-horizontal.png', $url);
    }

    public function test_brand_url_serves_favicon_from_cdn_when_remote(): void
    {
        $this->configurePublicCoversDisk();

        $url = AppStorage::brandUrl('favicon.png');

        $this->assertSame(
            'https://cdn.example.test/vehicle-maintenance/brand/revisalog/favicon.png',
            $url
        );
    }

    public function test_landing_url_uses_public_r2_base_when_remote(): void
    {
        $this->configurePublicCoversDisk();

        $url = AppStorage::landingUrl('app-vehicle.png');

        $this->assertSame(
            'https://cdn.example.test/vehicle-maintenance/landing/app-vehicle.png',
            $url
        );
    }

    public function test_landing_url_falls_back_to_local_asset_when_not_remote(): void
    {
        Config::set('filesystems.covers_disk', 'public');
        Storage::fake('public');

        $url = AppStorage::landingUrl('app-vehicle.png');

        $this->assertStringContainsString('/images/landing/app-vehicle.png', $url);
    }

    public function test_local_copies_prefers_public_http_for_remote_covers(): void
    {
        $this->configurePublicCoversDisk();
        Cache::flush();

        $jpeg = $this->solidJpeg();
        $path = 'vehicle-covers/capa.jpg';
        $publicUrl = AppStorage::coversUrl($path);

        Http::preventStrayRequests();
        Http::fake([
            $publicUrl => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $copies = AppStorage::localCopies([$path, $path]);

        $this->assertCount(1, $copies);
        $this->assertArrayHasKey($path, $copies);
        $this->assertTrue($copies[$path]['temporary']);
        $this->assertSame($jpeg, $copies[$path]['content'] ?? null);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === $publicUrl);
    }

    public function test_public_object_options_include_cache_control_and_visibility(): void
    {
        $options = AppStorage::publicObjectOptions();

        $this->assertSame('public', $options['visibility']);
        $this->assertSame(AppStorage::PUBLIC_CACHE_CONTROL, $options['CacheControl']);
    }

    public function test_put_public_writes_to_covers_disk_with_cache_headers(): void
    {
        $this->fakeCoversDisk('r2');

        $jpeg = $this->solidJpeg();
        $path = AppStorage::COVERS_PREFIX.'1_test.jpg';

        AppStorage::putPublic($path, $jpeg);

        Storage::disk('r2')->assertExists($path);
    }

    public function test_put_public_rejects_ineligible_paths(): void
    {
        $this->fakeCoversDisk('r2');

        $this->expectException(\InvalidArgumentException::class);

        AppStorage::putPublic('maintenance-photos/1.jpg', 'bytes');
    }

    public function test_put_public_accepts_landing_paths(): void
    {
        $this->fakeCoversDisk('r2');

        $path = AppStorage::LANDING_PREFIX.'app-vehicle.png';

        AppStorage::putPublic($path, 'bytes');

        Storage::disk('r2')->assertExists($path);
    }

    public function test_local_copies_uses_local_disk_without_http(): void
    {
        $this->fakeCoversDisk('public');
        Config::set('filesystems.default', 'public');

        $jpeg = $this->solidJpeg();
        $path = 'vehicle-covers/capa.jpg';
        Storage::disk('public')->put($path, $jpeg);

        Http::preventStrayRequests();
        Http::fake();

        $copies = AppStorage::localCopies([$path]);

        $this->assertArrayHasKey($path, $copies);
        $this->assertFalse($copies[$path]['temporary']);
        Http::assertNothingSent();
    }

    private function solidJpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        imagefilledrectangle($image, 0, 0, 7, 7, imagecolorallocate($image, 20, 64, 175));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        $jpeg = ob_get_clean();
        $this->assertIsString($jpeg);

        return $jpeg;
    }
}
