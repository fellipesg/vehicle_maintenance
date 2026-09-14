<?php

namespace Tests;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function fakeCoversDisk(string $disk = 'covers'): void
    {
        Config::set('filesystems.covers_disk', $disk);
        Storage::fake($disk);
        Config::set("filesystems.disks.{$disk}.driver", 'local');
    }

    protected function configurePublicCoversDisk(string $publicBase = 'https://cdn.example.test/vehicle-maintenance'): void
    {
        Config::set('filesystems.covers_disk', 'r2');
        Config::set('filesystems.disks.r2', [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://fake-account.r2.cloudflarestorage.com',
            'url' => $publicBase,
            'visibility' => 'public',
            'use_path_style_endpoint' => true,
            'throw' => false,
        ]);
    }

    protected function actingAsApiUser(?User $user = null): User
    {
        $user = $user ?? User::factory()->asUser()->create();
        $user->refresh();
        Sanctum::actingAs($user, SanctumMobileToken::ABILITIES);

        return $user;
    }

    protected function attachVehicleToUser(User $user, Vehicle $vehicle): void
    {
        $user->refresh();

        $user->vehicles()->attach($vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    protected function countQueries(callable $callback): int
    {
        $queryCount = 0;

        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $callback();

        return $queryCount;
    }
}
