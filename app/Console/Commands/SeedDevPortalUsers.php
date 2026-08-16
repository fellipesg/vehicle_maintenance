<?php

namespace App\Console\Commands;

use Database\Seeders\DevPortalUsersSeeder;
use Illuminate\Console\Command;

class SeedDevPortalUsers extends Command
{
    protected $signature = 'dev:seed-portal-users';

    protected $description = 'Seed idempotent dev login accounts for usuario, lojista, and admin portals';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command only runs in local or testing environments.');

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => DevPortalUsersSeeder::class,
        ]);

        return self::SUCCESS;
    }
}
