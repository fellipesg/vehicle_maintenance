<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DevPortalUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevPortalUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_in_testing_environment(): void
    {
        $seeder = new DevPortalUsersSeeder;

        $seeder->run();
        $countAfterFirstRun = User::count();

        $seeder->run();
        $countAfterSecondRun = User::count();

        $this->assertSame(3, $countAfterFirstRun);
        $this->assertSame(3, $countAfterSecondRun);

        $owner = User::where('email', DevPortalUsersSeeder::OWNER_EMAIL)->first();
        $garage = User::where('email', DevPortalUsersSeeder::GARAGE_EMAIL)->first();
        $admin = User::where('email', DevPortalUsersSeeder::ADMIN_EMAIL)->first();

        $this->assertNotNull($owner);
        $this->assertNotNull($garage);
        $this->assertNotNull($admin);

        $this->assertTrue($owner->isUser());
        $this->assertFalse($owner->isAdmin());
        $this->assertNotNull($owner->tenant_id);

        $this->assertTrue($garage->isGarage());
        $this->assertFalse($garage->isAdmin());
        $this->assertNotNull($garage->tenant_id);
        $this->assertNotNull($garage->garage);

        $this->assertTrue($admin->isUser());
        $this->assertTrue($admin->isAdmin());
        $this->assertNotNull($admin->tenant_id);

        $this->assertTrue(Hash::check(DevPortalUsersSeeder::PASSWORD, $owner->password));
        $this->assertTrue(Hash::check(DevPortalUsersSeeder::PASSWORD, $garage->password));
        $this->assertTrue(Hash::check(DevPortalUsersSeeder::PASSWORD, $admin->password));
    }

    public function test_seeder_does_not_run_in_production_environment(): void
    {
        $originalEnv = app()->environment();

        app()->detectEnvironment(fn (): string => 'production');

        try {
            (new DevPortalUsersSeeder)->run();

            $this->assertSame(0, User::count());
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }
}
