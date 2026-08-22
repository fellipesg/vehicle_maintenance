<?php

namespace Tests;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function fakeCoversDisk(string $disk = 'covers'): void
    {
        Config::set('filesystems.covers_disk', $disk);
        Storage::fake($disk);
    }

    protected function actingAsApiUser(?User $user = null): User
    {
        $user = $user ?? User::factory()->asUser()->create();
        $user->refresh();
        Sanctum::actingAs($user);

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
}
