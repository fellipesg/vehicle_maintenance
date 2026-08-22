<?php

namespace Tests\Unit;

use App\Support\AppStorage;
use Illuminate\Support\Facades\Config;
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
}
